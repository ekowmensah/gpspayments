<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Payment Verification Model
 */
class PaymentVerification extends BaseModel {
    protected string $table = 'payment_verifications';
    protected array $fillable = [
        'payment_id',
        'duplicate_check',
        'duplicate_check_result',
        'amount_validation',
        'amount_validation_result',
        'status_check',
        'status_check_result',
        'fraud_check',
        'fraud_check_result',
        'verification_result',
        'failure_reason',
        'manually_verified',
        'manual_verified_by',
        'manual_verified_at',
        'manual_verification_notes'
    ];

    public function getByPaymentId(int $paymentId): array {
        return $this->where('payment_id', '=', $paymentId)->orderBy('created_at', 'DESC')->get();
    }
}

