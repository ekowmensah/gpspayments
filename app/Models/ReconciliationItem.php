<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'payment_id',
        'action',
        'expected_amount',
        'recorded_amount',
        'corrected_amount',
        'discrepancy_reason',
        'resolution_note',
        'created_at',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ReconciliationBatch::class, 'batch_id');
    }
}

