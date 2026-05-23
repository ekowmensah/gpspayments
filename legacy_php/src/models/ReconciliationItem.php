<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Reconciliation Item Model
 */
class ReconciliationItem extends BaseModel {
    protected string $table = 'reconciliation_items';
    protected array $fillable = [
        'batch_id',
        'payment_id',
        'action',
        'original_amount',
        'corrected_amount',
        'discrepancy_reason'
    ];

    public function getByBatchId(int $batchId): array {
        return $this->where('batch_id', '=', $batchId)->orderBy('id', 'ASC')->get();
    }
}

