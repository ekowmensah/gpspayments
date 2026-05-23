<?php
declare(strict_types=1);

use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationItem;
use App\Services\ReconciliationService;
use App\Utils\Logger;
use PHPUnit\Framework\TestCase;

final class ReconciliationServiceTest extends TestCase {
    public function testCloseCashBatchMarksPaymentsConfirmedWhenBalanced(): void {
        $batchModel = new class extends ReconciliationBatch {
            public array $updatedData = [];
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'status' => 'Open']; }
            public function update(int $id, array $data): bool { $this->updatedData = $data; return true; }
        };

        $itemModel = new class extends ReconciliationItem {
            public function __construct() {}
            public function getByBatchId(int $batchId): array {
                return [
                    ['payment_id' => 11, 'action' => 'Include', 'original_amount' => 30.00, 'corrected_amount' => null],
                    ['payment_id' => 12, 'action' => 'Include', 'original_amount' => 20.00, 'corrected_amount' => null],
                ];
            }
        };

        $paymentModel = new class extends Payment {
            public array $updatedPayments = [];
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'amount' => 20.00]; }
            public function update(int $id, array $data): bool { $this->updatedPayments[$id] = $data; return true; }
        };

        $svc = new ReconciliationService(new Logger(), $batchModel, $itemModel, $paymentModel);
        $result = $svc->closeCashBatch(7, 50.00, 'Balanced', 99);

        $this->assertTrue($result['success']);
        $this->assertSame('Closed', $result['status']);
        $this->assertArrayHasKey(11, $paymentModel->updatedPayments);
        $this->assertArrayHasKey(12, $paymentModel->updatedPayments);
        $this->assertSame('Confirmed', $paymentModel->updatedPayments[11]['status']);
    }
}

