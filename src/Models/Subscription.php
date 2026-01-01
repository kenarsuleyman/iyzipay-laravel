<?php

namespace Iyzico\IyzipayLaravel\Models;

use Iyzico\IyzipayLaravel\Casts\PlanCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
	use SoftDeletes;

    protected $fillable = [
        'next_charge_amount',
        'currency',
        'next_charge_at',
        'plan'
    ];

    protected $casts = [
        'next_charge_at'     => 'datetime',
        'canceled_at'        => 'datetime',
        'next_charge_amount' => 'decimal:2',
        'plan'               => PlanCast::class,
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('canceled_at')
                     ->where('next_charge_at', '>=', Carbon::now());
    }

    public function scopeNotPaid($query)
    {
        return $query->whereNull('canceled_at')
                     ->where('next_charge_at', '<', Carbon::now());
    }

    public function owner(): BelongsTo
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return $this->belongsTo($modelClass, 'billable_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function cancel(): self
    {
        $this->canceled_at = Carbon::now();
        $this->save();

        return $this;
    }

    public function canceled(): bool
    {
        return ! empty($this->canceled_at);
    }

}
