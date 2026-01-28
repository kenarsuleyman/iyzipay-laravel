<?php

namespace Iyzico\IyzipayLaravel\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Iyzico\IyzipayLaravel\Enums\TransactionType;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionVoidException;
use Iyzico\IyzipayLaravel\Models\Transaction;
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;

class ReverseVerificationTransactions extends Command
{
    protected $signature = 'iyzipay:reverse_verifications';

    protected $description = 'Automatically voids or refunds card verification transactions.';

    public function handle(): void
    {
        $this->info('Starting verification reversal process...');

        $transactions = Transaction::query()
            ->where('type', TransactionType::VERIFICATION)
            ->whereNull('voided_at')
            ->whereNull('refunded_at')
            ->orderBy('id')
            ->where('created_at', '<=', Carbon::now()->subMinutes(5))
            ->cursor();

        $count = 0;

        foreach ($transactions as $transaction) {
            $this->processTransaction($transaction);
            $count++;
        }

        $this->info("Processed {$count} verification transactions.");
    }

    protected function processTransaction(Transaction $transaction): void
    {
        try {
            $this->line("Processing Transaction ID: {$transaction->id}...");

            try {
                IyzipayLaravel::cancel($transaction);
                $this->info(" -> Voided successfully.");
                return;

            } catch (TransactionVoidException $e) {
                $this->warn(" -> Void failed (Likely settled): " . $e->getMessage());
            }

            IyzipayLaravel::refund($transaction);

            $this->info(" -> Refunded successfully.");

        } catch (\Throwable $e) {
            Log::error("Failed to reverse verification transaction #{$transaction->id}: " . $e->getMessage());
            $this->error(" -> FAILED: " . $e->getMessage());
        }
    }
}
