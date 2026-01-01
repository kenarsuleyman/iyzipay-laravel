<?php

namespace Iyzico\IyzipayLaravel;

use Iyzico\IyzipayLaravel\Exceptions\Card\CardRemoveException;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardSaveException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionSaveException;
use Iyzico\IyzipayLaravel\Models\CreditCard;
use Iyzico\IyzipayLaravel\Models\Subscription;
use Iyzico\IyzipayLaravel\Models\Transaction;
use Iyzico\IyzipayLaravel\StorableClasses\BillFields;
use Iyzico\IyzipayLaravel\StorableClasses\Plan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;
use Iyzipay\Model\ThreedsInitialize;
use Iyzipay\Model\BkmInitialize;

trait Payable
{

    public function initializePayable(): void
    {
        $this->mergeCasts([
            'bill_fields' => BillFields::class,
        ]);
    }

    /**
     * Credit card relationship for the payable model
     *
     * @return HasMany
     */
    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class, 'billable_id');
    }

    /**
     * Transaction relationship for the payable model
     *
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'billable_id');
    }

    /**
     * Payable can have many subscriptions
     *
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'billable_id');
    }

    /**
     * Add credit card for payable
     *
     * @param array $attributes
     * @return CreditCard
     * @throws CardSaveException
     */
    public function addCreditCard(array $attributes = []): CreditCard
    {
        return IyzipayLaravel::addCreditCard($this, $attributes);
    }

    /**
     * Remove credit card credentials from the payable
     *
     * @param CreditCard $creditCard
     * @return bool
     * @throws CardRemoveException
     */
    public function removeCreditCard(CreditCard $creditCard): bool
    {
        if ( ! $this->creditCards->contains($creditCard)) {
            throw new CardRemoveException('This card does not belong to member!');
        }

        return IyzipayLaravel::removeCreditCard($creditCard);
    }

    /**
     * Single payment for the payable
     *
     * @param Collection $products
     * @param CreditCard $creditCard
     * @param string $currency
     * @param int $installment
     * @param bool $subscription
     * @return Transaction
     * @throws TransactionSaveException
     */
    public function pay(Collection $products, CreditCard $creditCard, $currency = 'TRY', $installment = 1, bool $subscription = false): Transaction
    {
        return IyzipayLaravel::singlePayment($this, $products, $creditCard, $currency, $installment, $subscription);
    }

	/**
	 * @param Collection $products
	 * @param CreditCard $creditCard
	 * @param string     $currency
	 * @param int        $installment
	 * @param bool       $subscription
	 * @throws
	 * @return ThreedsInitialize
	 */
	public function securePay(Collection $products, CreditCard $creditCard, $currency = 'TRY', $installment = 1, $subscription = false): ThreedsInitialize
	{
		return IyzipayLaravel::initializeThreeds($this, $products, $creditCard, $currency, $installment, $subscription);
    }

    public function payWithBKM(Collection $products, $currency = 'TRY', $installment = 1, $subscription = false): BkmInitialize
	{
		return IyzipayLaravel::initializeBkm($this, $products, $currency, $installment, $subscription);
    }

    /**
     * Subscribe to a plan.
     * @param Plan $plan
     * @param CreditCard $creditCard
     * @return ThreedsInitialize
     */
    public function subscribe(Plan $plan, CreditCard $creditCard): ThreedsInitialize
    {
        $this->subscriptions()->save(
            new Subscription([
                'next_charge_amount' => $plan->price,
                'currency'           => $plan->currency,
                'next_charge_at'     => Carbon::now()->addDays($plan->trialDays)->startOfDay(),
                'plan'               => $plan
            ])
        );

        return $this->paySubscription($creditCard);
    }

    /**
     * Check if payable subscribe to a plan
     *
     * @param Plan $plan
     * @return bool
     */
    public function isSubscribeTo(Plan $plan): bool
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->plan->id == $plan->id && !$subscription->canceled())
            {
                return $subscription->next_charge_at > Carbon::today()->startOfDay();
            }
        }

        return false;
    }

    /**
     * Payment for the subscriptions of payable
     */
    public function paySubscription(CreditCard $creditCard): ThreedsInitialize
    {
	    $this->load('subscriptions');

        foreach ($this->subscriptions as $subscription) {
            if ($subscription->canceled() || $subscription->next_charge_at > Carbon::today()->startOfDay()) {
                continue;
            }

            if ($subscription->next_charge_amount > 0) {
                $transaction = $this->securePay(collect([$subscription->plan]), $creditCard, $subscription->plan->currency, 1, true);
	            session()->flash('iyzico.subscription', $subscription);

                return $transaction;
//                $transaction->subscription()->associate($subscription);
//                $transaction->save();
            }

//            $subscription->next_charge_at = $subscription->next_charge_at->addMonths(($subscription->plan->interval == 'yearly') ? 12 : 1);
//            $subscription->save();
        }
    }

    /**
     * Check payable can have bill fields.
     *
     * @return bool
     */
    public function isBillable(): bool
    {
        // Because of the Cast, $this->bill_fields is now a BillFields Object or null.
        return $this->bill_fields instanceof BillFields;
    }
}
