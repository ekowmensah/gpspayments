<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationBatch extends Model
{
    protected $fillable = [
        'association_id',
        'reconciliation_reference',
        'reconciliation_type',
        'period_start',
        'period_end',
        'expected_total',
        'recorded_total',
        'discrepancy_total',
        'status',
        'reconciled_by',
        'reviewed_by',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'batch_id');
    }
}

