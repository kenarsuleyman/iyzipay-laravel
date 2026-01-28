<?php

namespace Iyzico\IyzipayLaravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Iyzico\IyzipayLaravel\Casts\ProductCollectionCast;
use Iyzico\IyzipayLaravel\Enums\TransactionStatus;
use Iyzico\IyzipayLaravel\Enums\TransactionType;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionRefundException;
use Iyzico\IyzipayLaravel\Exceptions\Transaction\TransactionVoidException;
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
	use SoftDeletes;

    protected $fillable = [
        'amount',
        'products',
        'refunds',
        'iyzipay_key',
        'iyzipay_transaction_id',
        'voided_at',
        'currency',
        'status',
        'error',
        'refunded_at',
        'type',
        'installment'
    ];

    protected $casts = [
        'status'    => TransactionStatus::class,
        'type'      => TransactionType::class,
        'products'  => ProductCollectionCast::class,
        'refunds'   => 'array',
        'error'     => 'array',
        'voided_at' => 'datetime',
        'amount'    => 'decimal:2',
    ];

    protected $appends = [
        'refunded_amount'
    ];

	public function scopeSuccess(Builder $query): void
	{
		$query->where('status', TransactionStatus::SUCCESS->value);
	}

	public function scopeFailure(Builder $query): void
	{
		$query->where('status', TransactionStatus::FAILED->value);
	}

    public function billable(): BelongsTo
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return $this->belongsTo($modelClass, 'billable_id');
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @throws TransactionVoidException
     */
    public function cancel(): Transaction
    {
        if ($this->created_at < Carbon::today()->startOfDay()) {
            throw new TransactionVoidException('This transaction cannot be voided.');
        }

        return IyzipayLaravel::cancel($this);
    }

    /**
     * Refund this transaction (Full or Partial).
     *
     * @param float|null $amount The amount to refund. If null, refunds the full remaining balance.
     *
     * @return Transaction The updated transaction instance with refund details.
     *
     * @throws \InvalidArgumentException If the amount is <= 0 or exceeds the remaining balance.
     * @throws TransactionRefundException If the refund operation fails at the payment gateway.
     */
    public function refund(?float $amount = null): Transaction
    {
        return IyzipayLaravel::refund($this, $amount);
    }

    protected function refundedAmount(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                if (empty($this->refunds)) {
                    return 0.0;
                }

                return (float) array_sum(array_column($this->refunds, 'amount'));
            }
        );
    }
}
