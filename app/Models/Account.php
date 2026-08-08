<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'bank_name',
        'account_number_last4',
        'opening_balance',
        'currency',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    protected $appends = ['current_balance'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Transfers received into this account (transactions where it's the destination). */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    public function getCurrentBalanceAttribute(): float
    {
        $outgoing = $this->transactions()->whereIn('type', ['expense', 'transfer'])->sum('amount');
        $incoming = $this->transactions()->where('type', 'income')->sum('amount');
        $transfersIn = $this->incomingTransfers()->where('type', 'transfer')->sum('amount');

        return round((float) $this->opening_balance + (float) $incoming + (float) $transfersIn - (float) $outgoing, 2);
    }
}
