<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Member;
use App\Models\CollectionItem;
use App\Models\PaymentVerification;
use App\Utils\Logger;

/**
 * Payment Verification Service
 * Responsible for validating payment data and flagging suspicious activity.
 */
class PaymentVerificationService {
    public const RESULT_PASS = 'Pass';
    public const RESULT_FAIL = 'Fail';

    private Payment $paymentModel;
    private Member $memberModel;
    private CollectionItem $collectionItemModel;
    private PaymentVerification $paymentVerificationModel;
    private Logger $logger;

    public function __construct(
        Logger $logger,
        ?Payment $paymentModel = null,
        ?Member $memberModel = null,
        ?CollectionItem $collectionItemModel = null,
        ?PaymentVerification $paymentVerificationModel = null
    ) {
        $this->paymentModel = $paymentModel ?? new Payment();
        $this->memberModel = $memberModel ?? new Member();
        $this->collectionItemModel = $collectionItemModel ?? new CollectionItem();
        $this->paymentVerificationModel = $paymentVerificationModel ?? new PaymentVerification();
        $this->logger = $logger;
    }

    /**
     * Verify a payment and return a structured result with all checks.
     */
    public function verify(array $payment): array {
        $paymentId = $payment['id'] ?? null;
        $this->logger->debug('Payment verification started', ['payment_id' => $paymentId]);

        $checks = [
            'amount' => $this->validateAmount($payment),
            'member_status' => $this->checkMemberStatus($payment),
            'duplicate' => $this->checkDuplicate($payment),
            'fraud' => $this->detectFraud($payment)
        ];

        $result = self::RESULT_PASS;
        $failureReason = null;

        foreach ($checks as $check) {
            if (!$check['passed']) {
                $result = self::RESULT_FAIL;
                $failureReason = $check['message'];
                break;
            }
        }

        $verificationId = null;
        if (!empty($paymentId)) {
            $verificationId = $this->paymentVerificationModel->create([
                'payment_id' => (int)$paymentId,
                'duplicate_check' => 1,
                'duplicate_check_result' => $checks['duplicate']['passed'] ? 1 : 0,
                'amount_validation' => 1,
                'amount_validation_result' => $checks['amount']['passed'] ? 1 : 0,
                'status_check' => 1,
                'status_check_result' => $checks['member_status']['passed'] ? 1 : 0,
                'fraud_check' => 1,
                'fraud_check_result' => $checks['fraud']['passed'] ? 1 : 0,
                'verification_result' => $result,
                'failure_reason' => $failureReason
            ]);
        }

        $this->logger->info('Verification result', [
            'result' => $result,
            'failure_reason' => $failureReason,
            'checks' => $checks,
            'verification_id' => $verificationId
        ]);

        return [
            'verification_id' => $verificationId,
            'verification_result' => $result,
            'failure_reason' => $failureReason,
            'checks' => $checks
        ];
    }

    private function validateAmount(array $payment): array {
        $amount = $payment['amount'] ?? null;

        if ($amount === null || !is_numeric($amount)) {
            return [
                'passed' => false,
                'message' => 'Amount is required and must be numeric'
            ];
        }

        $amount = (float)$amount;

        if ($amount <= 0.0) {
            return [
                'passed' => false,
                'message' => 'Amount must be greater than zero'
            ];
        }

        if ($amount < 0.01 || $amount > 100000) {
            return [
                'passed' => false,
                'message' => 'Amount must be between 0.01 and 100000'
            ];
        }

        $collectionItemId = $payment['collection_item_id'] ?? null;

        if (!empty($collectionItemId) && is_numeric($collectionItemId)) {
            $item = $this->collectionItemModel->find((int)$collectionItemId);

            if ($item && isset($item['amount']) && is_numeric($item['amount'])) {
                $expected = (float)$item['amount'];

                if ($expected > 0.0) {
                    $overpaymentPercent = (($amount - $expected) / $expected) * 100;

                    if ($overpaymentPercent > OVERPAYMENT_THRESHOLD_PERCENT) {
                        return [
                            'passed' => false,
                            'message' => 'Amount exceeds expected amount by ' . round($overpaymentPercent, 2) . '%'
                        ];
                    }
                }
            }
        }

        return [
            'passed' => true,
            'message' => 'Amount is valid'
        ];
    }

    private function checkMemberStatus(array $payment): array {
        $memberId = $payment['member_id'] ?? null;

        if ($memberId === null || !is_numeric($memberId)) {
            return [
                'passed' => false,
                'message' => 'Member identifier is missing or invalid'
            ];
        }

        $member = $this->memberModel->find((int)$memberId);

        if (!$member) {
            return [
                'passed' => false,
                'message' => 'Member not found'
            ];
        }

        if (($member['status'] ?? '') !== 'Active') {
            return [
                'passed' => false,
                'message' => 'Member status is ' . ($member['status'] ?? 'Unknown')
            ];
        }

        return [
            'passed' => true,
            'message' => 'Member is active'
        ];
    }

    private function checkDuplicate(array $payment): array {
        $memberId = $payment['member_id'] ?? null;
        $paymentMethod = $payment['payment_method'] ?? null;
        $amount = $payment['amount'] ?? null;
        $collectionItemId = $payment['collection_item_id'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if ($memberId === null || $paymentMethod === null || $amount === null) {
            return [
                'passed' => false,
                'message' => 'Duplicate check failed: missing payment details'
            ];
        }

        $hours = DUPLICATE_DETECTION_HOURS;
        $since = date('Y-m-d H:i:s', strtotime("-$hours hours"));

        $query = $this->paymentModel
            ->where('member_id', '=', (int)$memberId)
            ->where('payment_method', '=', (string)$paymentMethod)
            ->where('amount', '=', (float)$amount)
            ->where('created_at', '>', $since)
            ->where('status', '!=', 'Failed');

        if (!empty($collectionItemId) && is_numeric($collectionItemId)) {
            $query = $query->where('collection_item_id', '=', (int)$collectionItemId);
        }

        if (!empty($paymentId) && is_numeric($paymentId)) {
            $query = $query->where('id', '!=', (int)$paymentId);
        }

        $duplicateCount = $query->count();

        return [
            'passed' => $duplicateCount === 0,
            'message' => $duplicateCount > 0
                ? 'Duplicate payment detected within ' . $hours . ' hours'
                : 'No duplicate detected'
        ];
    }

    private function detectFraud(array $payment): array {
        $memberId = $payment['member_id'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if ($memberId === null || !is_numeric($memberId)) {
            return [
                'passed' => false,
                'message' => 'Fraud check failed: missing member identifier'
            ];
        }

        $minutes = BURST_DETECTION_WINDOW_MINUTES;
        $limit = BURST_DETECTION_LIMIT;
        $since = date('Y-m-d H:i:s', strtotime("-$minutes minutes"));

        $query = $this->paymentModel
            ->where('member_id', '=', (int)$memberId)
            ->where('created_at', '>', $since)
            ->where('status', '!=', 'Failed');

        if (!empty($paymentId) && is_numeric($paymentId)) {
            $query = $query->where('id', '!=', (int)$paymentId);
        }

        $recentCount = $query->count();
        $recentCount++;

        if ($recentCount >= $limit) {
            return [
                'passed' => false,
                'message' => "Burst activity detected: $recentCount payments in $minutes minutes"
            ];
        }

        return [
            'passed' => true,
            'message' => 'No suspicious activity detected'
        ];
    }
}

