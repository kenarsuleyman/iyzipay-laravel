<?php

namespace Iyzico\IyzipayLaravel\Enums;

enum TransactionType: string
{
    case CHARGE = 'charge';
    case VERIFICATION = 'verification';
}
