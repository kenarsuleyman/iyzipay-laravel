<?php

namespace Iyzico\IyzipayLaravel;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Iyzico\IyzipayLaravel\DTOs\CardData;
use Iyzico\IyzipayLaravel\Enums\TransactionStatus;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardSaveException;
use Iyzico\IyzipayLaravel\Exceptions\Card\PayableMustHaveCreditCardException;
use Iyzico\IyzipayLaravel\Exceptions\Fields\BillFieldsException;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardRemoveException;
use Iyzico\IyzipayLaravel\Exceptions\Fields\CreditCardFieldsException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionRefundException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionSaveException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionVoidException;
use Iyzico\IyzipayLaravel\Exceptions\Iyzipay\IyzipayAuthenticationException;
use Iyzico\IyzipayLaravel\Exceptions\Iyzipay\IyzipayConnectionException;
use Iyzico\IyzipayLaravel\Exceptions\Threeds\ThreedsCreateException;
use Iyzico\IyzipayLaravel\Exceptions\Threeds\ThreedsInitializeException;
use Iyzico\IyzipayLaravel\Exceptions\Threeds\BkmCreateException;
use Iyzico\IyzipayLaravel\Exceptions\Threeds\BkmInitializeException;
use Iyzico\IyzipayLaravel\Models\CreditCard;
use Iyzico\IyzipayLaravel\Models\Transaction;
use Iyzico\IyzipayLaravel\Traits\ManagesPlans;
use Iyzico\IyzipayLaravel\Traits\PreparesCreditCardRequest;
use Iyzico\IyzipayLaravel\Traits\PreparesTransactionRequest;
use Iyzico\IyzipayLaravel\Events\SubscriptionCharged;
use Iyzico\IyzipayLaravel\Events\SubscriptionChargeFailed;
use Iyzico\IyzipayLaravel\Events\ThreedsCancelCallback;
use Iyzico\IyzipayLaravel\Events\ThreedsCallback;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Iyzipay\Model\ApiTest;
use Iyzico\IyzipayLaravel\Enums\TransactionType;
use Iyzico\IyzipayLaravel\Models\Subscription;
use Iyzipay\Model\Payment;
use Iyzipay\Model\BkmInitialize;
use Iyzipay\Model\ThreedsInitialize;
use Iyzipay\Options;
use Iyzipay\Model\Locale;
use Iyzico\IyzipayLaravel\PayableContract as Payable;

class IyzipayLaravel
{

	use PreparesCreditCardRequest, PreparesTransactionRequest, ManagesPlans;

	/**
	 * @var Options
	 */
	protected $apiOptions;


	/**
	 * IyzipayLaravel constructor.
	 *
	 * @throws IyzipayAuthenticationException
	 * @throws IyzipayConnectionException
	 */
	public function __construct ()
	{

		$this->initializeApiOptions();
		$this->checkApiOptions();
	}


    /**
     * Adds credit card for billable & payable model.
     *
     * @param PayableContract $payable
     * @param CardData|array $attributes
     *
     * @return CreditCard
     * @throws CardSaveException
     */
	public function addCreditCard (Payable $payable, CardData|array $attributes = []): CreditCard
	{

		$this->validateBillable( $payable );
		$this->validateCreditCardAttributes( $attributes );

		$card = $this->createCardOnIyzipay( $payable, $attributes );

		$creditCardModel = new CreditCard( [
			                                   'alias'      => $card->getCardAlias(),
			                                   'number'     => $card->getBinNumber(),
                                               'last_four'  => $card->getLastFourDigits(),
			                                   'token'      => $card->getCardToken(),
                                               'association'=> $card->getCardAssociation(),
                                               'verified'   => false,
			                                   'bank'       => $card->getCardBankName()
		                                   ] );
		$payable->creditCards()->save( $creditCardModel );

		$payable->iyzipay_key = $card->getCardUserKey();
		$payable->save();

		return $creditCardModel;
	}


	/**
	 * Remove credit card for billable & payable model.
	 *
	 * @param CreditCard $creditCard
	 *
	 * @return bool
	 * @throws CardRemoveException
	 */
	public function removeCreditCard (CreditCard $creditCard): bool
	{

		$this->removeCardOnIyzipay( $creditCard );
		$creditCard->delete();

		return TRUE;
	}


    /**
     * Process a Non-3DS Payment (Direct Charge).
     * * @param Transaction $transaction
     * * @return Transaction
     * @throws TransactionSaveException
     */
    public function singlePayment(Transaction $transaction): Transaction
    {
        // 1. Load Relations
        $user = $transaction->billable;
        $creditCard = $transaction->creditCard;

        // 2. Validations
        $this->validateBillable($user);
        $this->validateCreditCard($user, $creditCard);

        try {
            // 3. Prepare & Send Request (Logic extracted below)
            $payment = $this->createPaymentOnIyzipay($user, $creditCard, $transaction);

            // 4. Success Handling
            // Update the transaction with the official Iyzico ID and status
            $transaction->iyzipay_key = $payment->getPaymentId();
            $transaction->status      = TransactionStatus::SUCCESS;
            $transaction->error       = null; // Clear previous errors if any
            $transaction->save();

            // Mark card as verified after any successful payment
            if ($creditCard && !$creditCard->verified) {
                $creditCard->verified = true;
                $creditCard->save();
            }

            return $transaction;

        } catch (\Exception $e) {
            // 5. Failure Handling
            $transaction->status = TransactionStatus::FAILED;

            // Save the error message (or full JSON if you prefer)
            $transaction->error = [
                'message' => $e->getMessage(),
                'card'    => $creditCard->number
            ];

            $transaction->save();

            // Re-throw so the caller/scheduler knows it failed
            throw new TransactionSaveException($e->getMessage());
        }
    }


    /**
     * Process all due subscription payments.
     *
     * Finds subscriptions where next_charge_at has passed and charges them
     * using the owner's most recent credit card via non-3DS payment.
     *
     * Fires SubscriptionCharged on success and SubscriptionChargeFailed on failure.
     */
    public function processDuePayments(): void
    {
        $dueSubscriptions = Subscription::notPaid()->get();

        foreach ($dueSubscriptions as $subscription) {
            $this->processSubscriptionPayment($subscription);
        }
    }

    /**
     * Process a single subscription payment.
     */
    private function processSubscriptionPayment(Subscription $subscription): void
    {
        $payable = $subscription->owner;
        $creditCard = $payable->creditCards()->latest()->first();

        if (!$creditCard) {
            event(new SubscriptionChargeFailed($subscription));
            return;
        }

        $transaction = new Transaction([
            'amount'      => $subscription->next_charge_amount,
            'currency'    => $subscription->currency,
            'products'    => [$subscription->plan],
            'type'        => TransactionType::CHARGE,
            'status'      => TransactionStatus::PENDING,
            'installment' => 1,
        ]);

        $transaction->billable()->associate($payable);
        $transaction->creditCard()->associate($creditCard);
        $transaction->subscription()->associate($subscription);
        $transaction->save();

        try {
            $this->singlePayment($transaction);

            $nextChargeDate = $subscription->plan->interval === 'yearly'
                ? Carbon::now()->addYear()
                : Carbon::now()->addMonth();

            $subscription->next_charge_at = $nextChargeDate;
            $subscription->save();

            event(new SubscriptionCharged($subscription, $transaction));
        } catch (\Exception $e) {
            event(new SubscriptionChargeFailed($subscription, $transaction->fresh()));
        }
    }

    public function initializeThreeds(Transaction $transaction): ThreedsInitialize
    {
        // 1. Load Relations
        $user = $transaction->billable;
        $creditCard = $transaction->creditCard;

        // 2. Validations
        $this->validateBillable($user);
        $this->validateCreditCard($user, $creditCard);

        $callback = route('threeds.callback');

        try {
            // 3. Prepare Iyzico Request Logic (Extracted to keep this clean)
            // Pass $transaction to use its amount, currency, and ID.
            $threedsInitialize = $this->initializeThreedsOnIyzipay(
                $user,
                $creditCard,
                $transaction,
                $callback
            );

            // 4. Update Transaction with Iyzico Key
            // We now have the 'paymentId' or 'conversationId' from Iyzico to link them.
            $transaction->iyzipay_key = $threedsInitialize->getPaymentId();
            // Do NOT set status to SUCCESS yet. That happens in the callback.
            $transaction->save();

            return $threedsInitialize;

        } catch (\Exception $e) {
            // 5. Handle Errors Immediately
            $transaction->status = TransactionStatus::FAILED;
            $transaction->error = ['message' => $e->getMessage()];
            $transaction->save();

            throw $e; // Re-throw so the Controller can show the error
        }
    }

	/**
	 * Handle the 3DS callback after bank verification.
	 *
	 * @param Request $request
	 * @return Transaction
	 */
	public function threedsPayment(Request $request): Transaction
	{
		$transaction = Transaction::findOrFail($request->conversationId);

		try {
			$threedsPayment = $this->createThreedsPayment($request);

			// Update transaction with Iyzipay response
			$transaction->iyzipay_key = $threedsPayment->getPaymentId();
			$transaction->status = TransactionStatus::SUCCESS;
			$transaction->error = null;

			// Get the first payment item's transaction ID for refunds
			$paymentItems = $threedsPayment->getPaymentItems();
			if (!empty($paymentItems)) {
				$transaction->iyzipay_transaction_id = $paymentItems[0]->getPaymentTransactionId();
			}

			$transaction->save();

			// Mark card as verified after any successful payment
			if ($transaction->creditCard && !$transaction->creditCard->verified) {
				$transaction->creditCard->verified = true;
				$transaction->creditCard->save();
			}

			event(new ThreedsCallback($transaction));

			return $transaction;

		} catch (ThreedsCreateException $e) {
			$transaction->status = TransactionStatus::FAILED;
			$transaction->error = [
				'message' => $e->getMessage(),
				'card' => $transaction->creditCard?->number
			];
			$transaction->save();

			// If payment failed and this was a subscription, delete the subscription
			if ($transaction->subscription) {
				$transaction->subscription->delete();
			}

			event(new ThreedsCancelCallback($transaction));

			return $transaction;
		}
	}

	public function initializeBkm (
		Payable $payable, Collection $products, $currency, $installment, $subscription = FALSE
	): BkmInitialize {

		$this->validateBillable( $payable );

		$callback = route( 'bkm.callback' );

		$messages = []; // @todo: imporove here
		try {
			$transactionModel = $this->createTransactionModel( $payable, $products );

			$bkmInitialize = $this->initializeBKMOnIyzipay(
				$payable,
				compact( 'products', 'currency', 'installment', 'callback', 'transactionModel' ),
				$subscription
			);

			return $bkmInitialize;

		} catch (BkmInitializeException $e) {
			$messages[]               = $e->getMessage();
			$transactionModel->status = FALSE;
			$transactionModel->error  = $messages;
			$transactionModel->save();
		}

		throw new TransactionSaveException( implode( ', ', $messages ) );

	}


	/**
	 * @param Transaction $transactionModel
	 *
	 * @return Transaction
	 * @throws TransactionVoidException
	 */
	public function cancel (Transaction $transactionModel): Transaction
	{

		$cancel = $this->createCancelOnIyzipay( $transactionModel );

		$transactionModel->voided_at = Carbon::now();
		$refunds                     = $transactionModel->refunds;
		$refunds[]                   = [
			'type'        => 'void',
			'amount'      => $cancel->getPrice(),
			'iyzipay_key' => $cancel->getPaymentId()
		];

		$transactionModel->refunds = $refunds;
		$transactionModel->save();

		return $transactionModel;
	}


    /**
     * Refund a transaction (Full or Partial).
     *
     * @param Transaction $transactionModel
     * @param float|null $amount The amount to refund. If null, refunds the full remaining amount.
     *
     * @return Transaction
     * @throws InvalidArgumentException
     * @throws TransactionRefundException
     */
	public function refund (Transaction $transactionModel, ?float $amount = null): Transaction
	{

        //check previous partial-refunds for given transaction
        $remainingBalance = $transactionModel->amount - $transactionModel->refunded_amount;

        $amountToRefund = $amount ?? $remainingBalance;

        if ($amountToRefund <= 0) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        // Use epsilon comparison (0.0001) for float precision safety
        if ($amountToRefund > ($remainingBalance + 0.0001)) {
            throw new InvalidArgumentException(
                "Refund amount ({$amountToRefund}) exceeds the remaining refundable balance ({$remainingBalance})."
            );
        }

        $result = $this->createRefundOnIyzipay($transactionModel, $amountToRefund);

        $currentRefunds = $transactionModel->refunds ?? [];

        $actualRefundedAmount = (float) $result->getPrice();

        $currentRefunds[] = [
            'type'                      => 'refund',
            'amount'                    => (float) $result->getPrice(),
            'iyzipay_key'               => $result->getPaymentId(),
            'iyzipay_transaction_id'    => $result->getPaymentTransactionId(),
            'date'                      => Carbon::now()->toIso8601String()
        ];

		$transactionModel->refunds = $currentRefunds;

        $newTotalRefunded = $transactionModel->refunded_amount + $actualRefundedAmount;

        // Check if fully refunded (using epsilon for float safety)
        $isFullyRefunded = abs($transactionModel->amount - $newTotalRefunded) < 0.001;

        if ($isFullyRefunded) {
            $transactionModel->status = TransactionStatus::REFUNDED;
            $transactionModel->refunded_at = now();
        } else {
            $transactionModel->status = TransactionStatus::PARTIAL_REFUNDED;
        }

        $transactionModel->save();

		return $transactionModel;
	}


	/**
	 * Initializing API options with the given credentials.
	 */
	private function initializeApiOptions ()
	{

		$this->apiOptions = new Options();
		$this->apiOptions->setBaseUrl( config( 'iyzipay.baseUrl' ) );
		$this->apiOptions->setApiKey( config( 'iyzipay.apiKey' ) );
		$this->apiOptions->setSecretKey( config( 'iyzipay.secretKey' ) );
	}


	/**
	 * Check if api options has been configured successfully.
	 *
	 * @throws IyzipayAuthenticationException
	 * @throws IyzipayConnectionException
	 */
	private function checkApiOptions ()
	{

		try {
			$check = ApiTest::retrieve( $this->apiOptions );
		} catch (\Exception $e) {
			throw new IyzipayConnectionException();
		}

		if($check->getStatus() != 'success') {
			throw new IyzipayAuthenticationException();
		}
	}


	/**
	 * @param PayableContract $payable
	 *
	 * @throws BillFieldsException
	 */
	private function validateBillable (Payable $payable): void
	{

		if( ! $payable->isBillable()) {
			throw new BillFieldsException();
		}
	}


	/**
	 * @param PayableContract $payable
	 *
	 * @throws PayableMustHaveCreditCardException
	 */
	private function validateHasCreditCard (Payable $payable): void
	{

		if($payable->creditCards->isEmpty()) {
			throw new PayableMustHaveCreditCardException();
		}
	}


	/**
	 * @param PayableContract $payable
	 * @param CreditCard      $creditCard
	 *
	 * @throws PayableMustHaveCreditCardException
	 */
	private function validateCreditCard (Payable $payable, CreditCard $creditCard): void
	{

		if($creditCard->owner->id != $payable->id) {
			throw new PayableMustHaveCreditCardException();
		}
	}


	/**
	 * @param PayableContract $payable
	 * @param Collection      $products
	 * @param CreditCard      $creditCard
	 *
	 * @return Transaction
	 */
	private function createTransactionModel (
		Payable $payable,
		Collection $products,
		$creditCard = NULL
	): Transaction {

		$totalPrice = 0;
		foreach($products as $product) {
			$totalPrice += $product->getPrice();
		}

		$transactionModel = new Transaction( [
			                                     'amount'   => $totalPrice,
			                                     'products' => $products,
		                                     ] );

		if(!empty($creditCard)) {
			$transactionModel->creditCard()->associate( $creditCard );
		}

		$payable->transactions()->save( $transactionModel );

		return $transactionModel->fresh();
	}


	/**
	 * Handle 3DS payment cancellation (user cancelled at bank page).
	 *
	 * @param Request $request
	 * @return Transaction
	 */
	public function cancelThreedsPayment(Request $request): Transaction
	{
		$transaction = Transaction::findOrFail($request->conversationId);

		$transaction->status = TransactionStatus::FAILED;
		$transaction->error = ['message' => 'Ödeme iptal edildi'];
		$transaction->save();

		// If this was a subscription payment, delete the subscription
		if ($transaction->subscription) {
			$transaction->subscription->delete();
		}

		event(new ThreedsCancelCallback($transaction));

		return $transaction;
	}

	/**
	 * @return string
	 */
	protected function getLocale (): string
	{

		return (config( 'app.locale' ) == 'tr') ? Locale::TR : Locale::EN;
	}


	/**
	 * @return Options
	 */
	protected function getOptions (): Options
	{

		return $this->apiOptions;
	}
}
