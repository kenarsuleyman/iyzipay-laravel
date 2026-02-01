<?php

namespace Iyzico\IyzipayLaravel\Commands;

use Illuminate\Console\Command;
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;

class SubscriptionChargeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iyzipay:charge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due subscription payments';

    public function handle(): void
    {
        IyzipayLaravel::processDuePayments();
    }
}
