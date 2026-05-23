<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'member_charge_id',
        'allocated_amount',
        'allocation_order',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function memberCharge(): BelongsTo
    {
        return $this->belongsTo(MemberCharge::class, 'member_charge_id');
    }
}

