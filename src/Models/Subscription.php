<?php

namespace Iyzico\IyzipayLaravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Iyzico\IyzipayLaravel\Casts\PlanCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Iyzico\IyzipayLaravel\Enums\SubscriptionStatus;

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

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): SubscriptionStatus {
                $now = Carbon::now();

                if ($this->canceled_at !== null) {
                    if ($this->canceled_at > $now) {
                        return SubscriptionStatus::PENDING_CANCELLATION;
                    }
                    return SubscriptionStatus::CANCELED;
                }

                if ($this->next_charge_at !== null) {
                    if ($this->next_charge_at >= $now) {
                        return SubscriptionStatus::ACTIVE;
                    }
                    return SubscriptionStatus::OVERDUE;
                }

                return SubscriptionStatus::CANCELED;
            }
        );
    }

    protected function trialEndsAt(): Attribute
    {
        return Attribute::make(
            get: function (): ?Carbon {
                $trialDays = $this->plan?->trialDays ?? 0;

                if ($trialDays <= 0) {
                    return null;
                }

                return $this->created_at->copy()->addDays($trialDays);
            }
        );
    }

    protected function isTrial(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $trialEndsAt = $this->trial_ends_at;

                if ($trialEndsAt === null) {
                    return false;
                }

                return Carbon::now()->lt($trialEndsAt);
            }
        );
    }

    /**
     * Get subscriptions that are fully active and renewing.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('canceled_at')
            ->where('next_charge_at', '>=', Carbon::now());
    }

    /**
     * Get subscriptions that have been canceled but are still in the grace period.
     */
    public function scopePendingCancellation(Builder $query): void
    {
        $query->whereNotNull('canceled_at')
            ->where('canceled_at', '>', Carbon::now());
    }

    /**
     * Get subscriptions that are fully canceled (grace period ended).
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('canceled_at')
            ->where('canceled_at', '<=', Carbon::now());
    }

    /**
     * Get subscriptions that are active but payment failed/missed.
     */
    public function scopeNotPaid(Builder $query): void
    {
        $query->whereNull('canceled_at')
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

    /**
     * Graceful Cancellation: User keeps access until the end of the paid period.
     */
    public function cancel(): self
    {
        $this->canceled_at = $this->next_charge_at ?? Carbon::now();
        $this->next_charge_at = null;
        $this->save();
        return $this;
    }

    /**
     * Immediate Cancellation: User loses access instantly.
     * Useful for admins or fraud prevention.
     */
    public function forceCancel(): self
    {
        $this->canceled_at = Carbon::now();
        $this->next_charge_at = null;
        $this->save();
        return $this;
    }

    public function canceled(): bool
    {
        return ! empty($this->canceled_at) && $this->canceled_at <= Carbon::now();
    }

}
