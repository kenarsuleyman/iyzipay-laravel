<?php

namespace Iyzico\IyzipayLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
	use SoftDeletes;

    protected $fillable = [
        'alias',
        'number',
        'token',
        'bank',
        'last_four',
        'association',
    ];

    public function owner(): BelongsTo
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return $this->belongsTo($modelClass, 'billable_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
