<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Utils\Logger;
use App\Utils\SecurityHelper;
use App\Services\AuditService;

/**
 * Payment Service
 * Handles payment recording, verification, and reconciliation
 */
class PaymentService {
    private Payment $paymentModel;
    private PaymentVerificationService $verificationService;
    private AuditService $auditService;
    private Logger $logger;
    
    public function __construct(Logger $logger) {
        $this->paymentModel = new Payment();
        $this->verificationService = new PaymentVerificationService($logger);
        $this->auditService = new AuditService($logger);
        $this->logger = $logger;
    }
    
    /**
     * Record a cash payment
     */
    public function recordCashPayment(array $data): array {
        try {
            $this->logger->logPayment('CASH_PAYMENT_START', $data);
            
            $db = db();
            $db->begin_transaction();
            
            // Create payment record
            $paymentId = $this->paymentModel->create([
                'association_id' => $data['association_id'] ?? 1,
                'member_id' => $data['member_id'],
                'collection_item_id' => $data['collection_item_id'] ?? null,
                'amount' => $data['amount'],
                'payment_method' => 'Cash',
                'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
                'payment_time' => $data['payment_time'] ?? date('H:i:s'),
                'transaction_reference' => SecurityHelper::generateReference(),
                'receipt_number' => SecurityHelper::generateReceiptNumber(),
                'status' => 'Pending_Entry',
                'recorded_by' => $data['recorded_by'] ?? $_SESSION['user_id'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);
            
            if (!$paymentId) {
                $db->rollback();
                $this->logger->error('Failed to create payment');
                return [
                    'success' => false,
                    'message' => 'Failed to record payment'
                ];
            }
            
            // Get full payment record
            $payment = $this->paymentModel->find($paymentId);
            
            // Run verification
            $verification = $this->verificationService->verify($payment);
            
            // Update payment status based on verification
            if ($verification['verification_result'] === 'Pass') {
                $status = 'Pending_Reconciliation';
            } else {
                $status = 'Pending_Verification';
            }
            
            $updated = $this->paymentModel->update($paymentId, [
                'status' => $status
            ]);
            
            if (!$updated) {
                $db->rollback();
                $this->logger->error('Failed to update payment status after verification', ['payment_id' => $paymentId]);
                return [
                    'success' => false,
                    'message' => 'Failed to update payment status'
                ];
            }
            
            if (!$db->commit()) {
                $db->rollback();
                $this->logger->error('Failed to commit cash payment transaction', ['payment_id' => $paymentId]);
                return [
                    'success' => false,
                    'message' => 'Failed to finalize payment record'
                ];
            }
            
            $this->logger->logPayment('CASH_PAYMENT_RECORDED', [
                'payment_id' => $paymentId,
                'status' => $status,
                'verification' => $verification
            ]);

            $this->auditService->log(
                action: 'PAYMENT_RECORDED',
                entityType: 'Payment',
                entityId: (int)$paymentId,
                previousValue: null,
                newValue: json_encode([
                    'payment_method' => 'Cash',
                    'amount' => $data['amount'],
                    'status' => $status,
                    'verification_result' => $verification['verification_result'] ?? null
                ])
            );
            
            return [
                'success' => true,
                'message' => 'Payment recorded successfully',
                'payment_id' => $paymentId,
                'status' => $status,
                'receipt_number' => $payment['receipt_number'],
                'transaction_reference' => $payment['transaction_reference'],
                'verification' => $verification
            ];
            
        } catch (\Exception $e) {
            $this->auditService->log(
                action: 'PAYMENT_RECORD_FAILED',
                entityType: 'Payment',
                entityId: null,
                status: 'Failed',
                errorMessage: $e->getMessage()
            );
            $this->logger->error('Cash payment error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error recording payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Record a mobile money payment
     */
    public function recordMobileMoneyPayment(array $data): array {
        try {
            $this->logger->logPayment('MOMO_PAYMENT_START', $data);
            
            $db = db();
            $db->begin_transaction();
            
            // Create payment record
            $paymentId = $this->paymentModel->create([
                'association_id' => $data['association_id'] ?? 1,
                'member_id' => $data['member_id'],
                'collection_item_id' => $data['collection_item_id'] ?? null,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'Mobile Money',
                'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
                'payment_time' => $data['payment_time'] ?? date('H:i:s'),
                'transaction_reference' => $data['transaction_id'] ?? SecurityHelper::generateReference(),
                'receipt_number' => SecurityHelper::generateReceiptNumber(),
                'status' => 'Pending_Verification',
                'recorded_by' => $data['recorded_by'] ?? $_SESSION['user_id'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);
            
            if (!$paymentId) {
                $db->rollback();
                return [
                    'success' => false,
                    'message' => 'Failed to record payment'
                ];
            }
            
            // Get full payment
            $payment = $this->paymentModel->find($paymentId);
            
            // Run verification
            $verification = $this->verificationService->verify($payment);
            
            if ($verification['verification_result'] === 'Pass') {
                // Auto-confirm mobile money payments after verification
                $updated = $this->paymentModel->update($paymentId, [
                    'status' => 'Confirmed',
                    'verified_at' => date('Y-m-d H:i:s'),
                    'verified_by' => $_SESSION['user_id'] ?? null,
                    'confirmed_at' => date('Y-m-d H:i:s'),
                    'confirmed_by' => $_SESSION['user_id'] ?? null
                ]);
                $status = 'Confirmed';
            } else {
                $updated = $this->paymentModel->update($paymentId, [
                    'status' => 'Pending_Verification'
                ]);
                $status = 'Pending_Verification';
            }
            
            if (!$updated) {
                $db->rollback();
                $this->logger->error('Failed to update momo payment status after verification', ['payment_id' => $paymentId]);
                return [
                    'success' => false,
                    'message' => 'Failed to update payment status'
                ];
            }
            
            if (!$db->commit()) {
                $db->rollback();
                $this->logger->error('Failed to commit mobile money payment transaction', ['payment_id' => $paymentId]);
                return [
                    'success' => false,
                    'message' => 'Failed to finalize payment record'
                ];
            }
            
            $this->logger->logPayment('MOMO_PAYMENT_RECORDED', [
                'payment_id' => $paymentId,
                'status' => $status
            ]);

            $this->auditService->log(
                action: 'PAYMENT_RECORDED',
                entityType: 'Payment',
                entityId: (int)$paymentId,
                previousValue: null,
                newValue: json_encode([
                    'payment_method' => $data['payment_method'] ?? 'Mobile Money',
                    'amount' => $data['amount'],
                    'status' => $status,
                    'verification_result' => $verification['verification_result'] ?? null
                ])
            );
            
            return [
                'success' => true,
                'message' => 'Mobile money payment recorded',
                'payment_id' => $paymentId,
                'status' => $status,
                'receipt_number' => $payment['receipt_number'],
                'transaction_reference' => $payment['transaction_reference']
            ];
            
        } catch (\Exception $e) {
            $this->auditService->log(
                action: 'PAYMENT_RECORD_FAILED',
                entityType: 'Payment',
                entityId: null,
                status: 'Failed',
                errorMessage: $e->getMessage()
            );
            $this->logger->error('Mobile money payment error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error recording payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get payment by ID
     */
    public function getPayment(int $paymentId): ?array {
        return $this->paymentModel->find($paymentId);
    }
    
    /**
     * Get member payment history
     */
    public function getMemberPayments(int $memberId): array {
        return $this->paymentModel->getByMember($memberId);
    }
    
    /**
     * Get daily report
     */
    public function getDailyReport(string $date): array {
        $payments = $this->paymentModel->getByDate($date);
        
        $total = 0;
        $by_method = [];
        
        foreach ($payments as $payment) {
            if ($payment['status'] === 'Confirmed') {
                $total += $payment['amount'];
                $method = $payment['payment_method'];
                
                if (!isset($by_method[$method])) {
                    $by_method[$method] = [
                        'count' => 0,
                        'total' => 0
                    ];
                }
                
                $by_method[$method]['count']++;
                $by_method[$method]['total'] += $payment['amount'];
            }
        }
        
        return [
            'date' => $date,
            'total_confirmed' => $total,
            'payment_count' => count($payments),
            'confirmed_count' => count(array_filter($payments, fn($p) => $p['status'] === 'Confirmed')),
            'by_method' => $by_method,
            'payments' => $payments
        ];
    }
}
?>
