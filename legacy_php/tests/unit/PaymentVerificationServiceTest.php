<?php
declare(strict_types=1);

use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentVerification;
use App\Services\PaymentVerificationService;
use App\Utils\Logger;
use PHPUnit\Framework\TestCase;

final class PaymentVerificationServiceTest extends TestCase {
    public function testVerifyPassesAndStoresVerificationRecord(): void {
        $paymentModel = new class extends Payment {
            private array $countQueue = [0, 0];
            public function __construct() {}
            public function where(string $column, string $operator, $value): self { return $this; }
            public function count(): int { return array_shift($this->countQueue) ?? 0; }
        };

        $memberModel = new class extends Member {
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'status' => 'Active']; }
        };

        $collectionModel = new class extends CollectionItem {
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'amount' => 20.00]; }
        };

        $verificationModel = new class extends PaymentVerification {
            public array $lastCreated = [];
            public function __construct() {}
            public function create(array $data): ?int { $this->lastCreated = $data; return 101; }
        };

        $svc = new PaymentVerificationService(new Logger(), $paymentModel, $memberModel, $collectionModel, $verificationModel);
        $result = $svc->verify([
            'id' => 99,
            'member_id' => 5,
            'collection_item_id' => 7,
            'amount' => 20.00,
            'payment_method' => 'Cash'
        ]);

        $this->assertSame('Pass', $result['verification_result']);
        $this->assertSame(101, $result['verification_id']);
        $this->assertSame('Pass', $verificationModel->lastCreated['verification_result']);
    }

    public function testVerifyFailsOnExcessiveOverpayment(): void {
        $paymentModel = new class extends Payment {
            private array $countQueue = [0, 0];
            public function __construct() {}
            public function where(string $column, string $operator, $value): self { return $this; }
            public function count(): int { return array_shift($this->countQueue) ?? 0; }
        };

        $memberModel = new class extends Member {
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'status' => 'Active']; }
        };

        $collectionModel = new class extends CollectionItem {
            public function __construct() {}
            public function find($id): ?array { return ['id' => $id, 'amount' => 10.00]; }
        };

        $verificationModel = new class extends PaymentVerification {
            public function __construct() {}
            public function create(array $data): ?int { return 202; }
        };

        $svc = new PaymentVerificationService(new Logger(), $paymentModel, $memberModel, $collectionModel, $verificationModel);
        $result = $svc->verify([
            'id' => 100,
            'member_id' => 5,
            'collection_item_id' => 7,
            'amount' => 20.00,
            'payment_method' => 'Cash'
        ]);

        $this->assertSame('Fail', $result['verification_result']);
        $this->assertStringContainsString('exceeds expected amount', (string)$result['failure_reason']);
    }
}

