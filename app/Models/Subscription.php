<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'amount',
        'billing_cycle',
        'next_billing_date',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'next_billing_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function monthlyEquivalent(): float
    {
        return match ($this->billing_cycle) {
            'weekly' => (float) $this->amount * 52 / 12,
            'yearly' => (float) $this->amount / 12,
            default => (float) $this->amount,
        };
    }
}
