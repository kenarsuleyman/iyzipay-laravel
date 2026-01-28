<?php

namespace Iyzico\IyzipayLaravel\Enums;

enum TransactionStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUNDED = 'partial_refunded';
}
