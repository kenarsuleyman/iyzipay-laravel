<?php

namespace Iyzico\IyzipayLaravel;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Iyzico\IyzipayLaravel\Models\CreditCard;
use Iyzico\IyzipayLaravel\Models\Transaction;
use Iyzico\IyzipayLaravel\StorableClasses\Plan;
use Iyzipay\Model\ThreedsInitialize;
use Iyzipay\Model\BkmInitialize;

interface PayableContract
{
    public function getKey(); // Usually returns the ID
    public function isBillable(): bool;

    // Relationships
    public function creditCards(): HasMany;
    public function transactions(): HasMany;
    public function subscriptions(): HasMany;

    // Card Management
    public function addCreditCard(array $attributes = []): CreditCard;
    public function removeCreditCard(CreditCard $creditCard): bool;

    // Payments

    /**
     * Performs a standard, non-3DS payment (Auto-Charge).
     * Automatically creates the Transaction record.
     */
    public function pay(Collection $products, CreditCard $creditCard, string $currency = 'TRY', int $installment = 1): Transaction;

    /**
     * Initiates a 3D Secure payment flow.
     * Expects a pre-created Transaction model (from subscribe() or manually created).
     */
    public function securePay(Transaction $transaction): ThreedsInitialize;

    /**
     * Subscribes the user to a plan.
     * Handles Trial logic, creates the Subscription, creates the Transaction, and calls securePay.
     */
    public function subscribe(Plan $plan, CreditCard $creditCard): ThreedsInitialize;

    // Checks
    public function isSubscribeTo(Plan $plan): bool;

    // Legacy / To Be Refactored
    // You likely want to refactor BKM later to match the Transaction pattern too.
    public function payWithBKM(Collection $products, string $currency = 'TRY', int $installment = 1): BkmInitialize;
}
