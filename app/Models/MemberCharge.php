<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberCharge extends Model
{
    protected $fillable = [
        'association_id',
        'charge_reference',
        'member_id',
        'collection_item_id',
        'billing_period_id',
        'charge_run_id',
        'charge_date',
        'due_date',
        'expected_amount',
        'penalty_amount',
        'discount_amount',
        'waived_amount',
        'status',
        'status_updated_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'charge_date' => 'date',
        'due_date' => 'date',
        'status_updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'member_charge_id');
    }
}

