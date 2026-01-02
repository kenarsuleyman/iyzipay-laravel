<?php

namespace Iyzico\IyzipayLaravel\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PENDING_CANCELLATION = 'pending_cancellation';
    case CANCELED = 'canceled';
    case OVERDUE = 'overdue';
}
