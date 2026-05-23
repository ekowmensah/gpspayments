<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationItem;
use App\Utils\Logger;
use App\Services\AuditService;

/**
 * Reconciliation Service
 * Handles cash batch reconciliation.
 */
class ReconciliationService {
    private ReconciliationBatch $batchModel;
    private ReconciliationItem $itemModel;
    private Payment $paymentModel;
    private AuditService $auditService;
    private Logger $logger;

    public function __construct(
        Logger $logger,
        ?ReconciliationBatch $batchModel = null,
        ?ReconciliationItem $itemModel = null,
        ?Payment $paymentModel = null
    ) {
        $this->batchModel = $batchModel ?? new ReconciliationBatch();
        $this->itemModel = $itemModel ?? new ReconciliationItem();
        $this->paymentModel = $paymentModel ?? new Payment();
        $this->auditService = new AuditService($logger);
        $this->logger = $logger;
    }

    public function createBatch(array $data): array {
        $batchId = $this->batchModel->create([
            'association_id' => (int)($data['association_id'] ?? 1),
            'reconciliation_type' => $data['reconciliation_type'] ?? 'Cash_End_of_Day',
            'reconciliation_date' => $data['reconciliation_date'] ?? date('Y-m-d'),
            'reconciliation_time' => $data['reconciliation_time'] ?? date('H:i:s'),
            'start_time' => date('Y-m-d H:i:s'),
            'status' => 'Open',
            'reconciled_by' => $data['user_id'] ?? null,
            'notes' => $data['notes'] ?? null
        ]);

        if (!$batchId) {
            return ['success' => false, 'message' => 'Failed to create reconciliation batch'];
        }

        $this->logger->info('Reconciliation batch created', ['batch_id' => $batchId]);
        $this->auditService->log(
            action: 'RECONCILIATION_OPENED',
            entityType: 'ReconciliationBatch',
            entityId: (int)$batchId,
            newValue: json_encode([
                'reconciliation_type' => $data['reconciliation_type'] ?? 'Cash_End_of_Day',
                'reconciliation_date' => $data['reconciliation_date'] ?? date('Y-m-d')
            ])
        );
        return ['success' => true, 'batch_id' => $batchId];
    }

    public function addItemToBatch(int $batchId, int $paymentId, string $action = 'Include', ?float $correctedAmount = null, ?string $reason = null): array {
        $payment = $this->paymentModel->find($paymentId);
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $itemId = $this->itemModel->create([
            'batch_id' => $batchId,
            'payment_id' => $paymentId,
            'action' => $action,
            'original_amount' => (float)$payment['amount'],
            'corrected_amount' => $correctedAmount,
            'discrepancy_reason' => $reason
        ]);

        if (!$itemId) {
            return ['success' => false, 'message' => 'Failed to add item to batch'];
        }

        $this->auditService->log(
            action: 'RECONCILIATION_ITEM_ADDED',
            entityType: 'ReconciliationItem',
            entityId: (int)$itemId,
            newValue: json_encode([
                'batch_id' => $batchId,
                'payment_id' => $paymentId,
                'action' => $action
            ])
        );

        return ['success' => true, 'item_id' => $itemId];
    }

    public function closeCashBatch(int $batchId, float $recordedAmount, ?string $notes, int $closedBy): array {
        $batch = $this->batchModel->find($batchId);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found'];
        }

        $items = $this->itemModel->getByBatchId($batchId);
        $expectedAmount = 0.0;

        foreach ($items as $item) {
            if (($item['action'] ?? '') === 'Exclude') {
                continue;
            }

            $expectedAmount += (float)($item['corrected_amount'] ?? $item['original_amount'] ?? 0);
        }

        $discrepancy = $recordedAmount - $expectedAmount;
        $status = abs($discrepancy) < 0.01 ? 'Closed' : 'Pending_Review';

        $updated = $this->batchModel->update($batchId, [
            'total_expected' => $expectedAmount,
            'total_recorded' => $recordedAmount,
            'total_discrepancy' => $discrepancy,
            'status' => $status,
            'end_time' => date('Y-m-d H:i:s'),
            'closed_by' => $closedBy,
            'notes' => $notes
        ]);

        if (!$updated) {
            return ['success' => false, 'message' => 'Failed to close batch'];
        }

        if ($status === 'Closed') {
            foreach ($items as $item) {
                if (($item['action'] ?? '') !== 'Include') {
                    continue;
                }

                $paymentId = (int)($item['payment_id'] ?? 0);
                if ($paymentId <= 0) {
                    continue;
                }

                $this->paymentModel->update($paymentId, [
                    'status' => 'Confirmed',
                    'confirmed_at' => date('Y-m-d H:i:s'),
                    'confirmed_by' => $closedBy
                ]);
            }
        }

        $this->logger->info('Reconciliation batch closed', [
            'batch_id' => $batchId,
            'status' => $status,
            'discrepancy' => $discrepancy
        ]);

        $this->auditService->log(
            action: 'RECONCILIATION_CLOSED',
            entityType: 'ReconciliationBatch',
            entityId: $batchId,
            previousValue: json_encode(['status' => $batch['status'] ?? 'Open']),
            newValue: json_encode([
                'status' => $status,
                'expected_amount' => $expectedAmount,
                'recorded_amount' => $recordedAmount,
                'discrepancy' => $discrepancy
            ])
        );

        return [
            'success' => true,
            'batch_id' => $batchId,
            'status' => $status,
            'expected_amount' => $expectedAmount,
            'recorded_amount' => $recordedAmount,
            'discrepancy' => $discrepancy
        ];
    }

    public function getOpenBatches(): array {
        return $this->batchModel->getOpenBatches();
    }
}
