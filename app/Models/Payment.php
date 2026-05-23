<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'association_id',
        'payment_reference',
        'payment_intent_id',
        'member_id',
        'collection_item_id',
        'amount',
        'currency_code',
        'payment_method',
        'source',
        'transaction_reference',
        'provider_name',
        'provider_transaction_reference',
        'idempotency_key',
        'payment_date',
        'posting_date',
        'status',
        'reversal_reason',
        'notes',
        'recorded_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'posting_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
