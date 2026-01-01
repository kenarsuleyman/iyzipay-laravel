<?php

namespace Iyzico\IyzipayLaravel\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'voided_at',
        'currency',
        'status',
        'error'
    ];

    protected $casts = [
        'products'  => 'array',
        'refunds'   => 'array',
        'error'     => 'array',
        'voided_at' => 'datetime',
        'amount'    => 'decimal:2',
    ];

    protected $appends = [
        'refunded_amount'
    ];

	public function scopeSuccess($query)
	{
		return $query->where('status', true);
	}

	public function scopeFailure($query)
	{
		return $query->where('status', false);
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
     * @throws TransactionVoidException
     */
    public function refund(): Transaction
    {
        return IyzipayLaravel::cancel($this);
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
