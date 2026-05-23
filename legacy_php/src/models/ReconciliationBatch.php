<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Reconciliation Batch Model
 */
class ReconciliationBatch extends BaseModel {
    protected string $table = 'reconciliation_batches';
    protected array $fillable = [
        'association_id',
        'reconciliation_type',
        'reconciliation_date',
        'reconciliation_time',
        'start_time',
        'end_time',
        'total_expected',
        'total_recorded',
        'total_discrepancy',
        'status',
        'reconciled_by',
        'reviewed_by',
        'closed_by',
        'notes'
    ];

    public function getOpenBatches(): array {
        return $this->where('status', '=', 'Open')->orderBy('reconciliation_date', 'DESC')->get();
    }
}

