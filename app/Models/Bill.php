<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'amount',
        'due_date',
        'recurrence',
        'is_paid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date:Y-m-d',
        'is_paid' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
