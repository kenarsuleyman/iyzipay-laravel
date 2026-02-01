<?php

namespace Iyzico\IyzipayLaravel\Events;

use Iyzico\IyzipayLaravel\Models\Subscription;
use Iyzico\IyzipayLaravel\Models\Transaction;

class SubscriptionChargeFailed
{
    public function __construct(
        public Subscription $subscription,
        public ?Transaction $transaction = null,
    ) {}
}
