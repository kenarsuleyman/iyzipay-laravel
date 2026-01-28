<?php

namespace Iyzico\IyzipayLaravel;

use Iyzico\IyzipayLaravel\DTOs\CardData;
use Iyzico\IyzipayLaravel\Enums\TransactionStatus;
use Iyzico\IyzipayLaravel\Enums\TransactionType;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardRemoveException;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardSaveException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionSaveException;
use Iyzico\IyzipayLaravel\Models\CreditCard;
use Iyzico\IyzipayLaravel\Models\Subscription;
use Iyzico\IyzipayLaravel\Models\Transaction;
use Iyzico\IyzipayLaravel\StorableClasses\BillFields;
use Iyzico\IyzipayLaravel\StorableClasses\Plan;
use Iyzico\IyzipayLaravel\StorableClasses\Product;
use Iyzipay\Model\BasketItemType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
     * @param CardData|array $attributes
     * @return CreditCard
     * @throws CardSaveException
     */
    public function addCreditCard(CardData|array $attributes = []): CreditCard
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
     * Perform a single, non-3DS payment (Auto-Payment).
     *
     * @param Collection $products
     * @param CreditCard $creditCard
     * @param string $currency
     * @param int $installment
     * @return Transaction
     * @throws TransactionSaveException
     */
    public function pay(Collection $products, CreditCard $creditCard, $currency = 'TRY', $installment = 1): Transaction
    {
        // 1. Calculate Total Amount
        // We assume products have a getPrice() method (like your Plan model)
        $totalAmount = $products->reduce(function ($carry, $item) {
            return $carry + $item->getPrice();
        }, 0);

        // 2. Create the Transaction (PENDING)
        $transaction = new Transaction([
            'amount'      => $totalAmount,
            'currency'    => $currency,
            'installment' => $installment,
            'products'    => $products, // Casts to array automatically
            'type'        => TransactionType::CHARGE,
            'status'      => TransactionStatus::PENDING,
        ]);

        // 3. Associate Relationships
        $transaction->billable()->associate($this);
        $transaction->creditCard()->associate($creditCard);

        // If this method is called manually, there is no subscription_id.
        // If called via the Scheduler, the Scheduler will create the transaction itself
        // and call singlePayment() directly, skipping this wrapper.

        $transaction->save();

        // 4. Process Payment
        return IyzipayLaravel::singlePayment($transaction);
    }

    /**
     * @param Transaction $transaction
     * @return ThreedsInitialize
     * @throws \Exception
     */
    public function securePay(Transaction $transaction): ThreedsInitialize
    {
        return IyzipayLaravel::initializeThreeds($transaction);
    }

    public function payWithBKM(Collection $products, $currency = 'TRY', $installment = 1, $subscription = false): BkmInitialize
	{
		return IyzipayLaravel::initializeBkm($this, $products, $currency, $installment, $subscription);
    }

    /**
     * @throws \Exception
     */
    public function subscribe(Plan $plan, CreditCard $creditCard): ThreedsInitialize
    {
        $isTrial = $plan->trialDays > 0;

        // 1. Calculate the Next Charge Date for the Subscription
        // - If Trial: The customer pays nothing now (except verification). Next charge is after trial.
        // - If Paid: The customer pays for 1st month now. Next charge is next month.
        $nextChargeDate = $isTrial
            ? Carbon::now()->addDays($plan->trialDays)
            : Carbon::now()->addMonths($plan->interval == 'yearly' ? 12 : 1);

        // 2. Create the Subscription
        $subscription = $this->subscriptions()->create([
            'next_charge_amount' => $plan->price,
            'currency'           => $plan->currency,
            'next_charge_at'     => $nextChargeDate,
            'plan'               => $plan
        ]);

        // 3. Determine Transaction Details
        // - If Trial: We charge 1.00 TL to verify the card.
        // - If Paid: We charge the full Plan price.
        $isVerification = $isTrial && !$creditCard->verified;
        $amount = $isVerification ? 1.00 : $plan->price;
        $type   = $isVerification ? TransactionType::VERIFICATION : TransactionType::CHARGE;

        // For verification, create a 1 TL product (Iyzipay requires product total = transaction total)
        $products = $isVerification
            ? [new Product(
                id: 'card-verification',
                name: 'Kart Doğrulama',
                price: 1.00,
                category: 'Verification',
                type: BasketItemType::VIRTUAL
            )]
            : [$plan];

        // 4. Create the Transaction Record First (Pending)
        $transaction = new Transaction([
            'amount'   => $amount,
            'currency' => $plan->currency,
            'products' => $products,
            'type'     => $type,
            'status'   => TransactionStatus::PENDING,
        ]);

        // Associate Relationships
        $transaction->billable()->associate($this);
        $transaction->creditCard()->associate($creditCard);
        $transaction->subscription()->associate($subscription);
        $transaction->save();

        // 5. Initiate 3DS Payment
        // The callback will find the transaction by conversationId (transaction ID)
        return $this->securePay($transaction);
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
