<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Utils\Logger;

/**
 * Audit service for persistent audit trail events.
 */
class AuditService {
    private AuditLog $auditModel;
    private Logger $logger;

    public function __construct(Logger $logger, ?AuditLog $auditModel = null) {
        $this->logger = $logger;
        $this->auditModel = $auditModel ?? new AuditLog();
    }

    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        ?string $previousValue = null,
        ?string $newValue = null,
        string $status = 'Success',
        ?string $errorMessage = null,
        ?int $userId = null,
        ?string $userRole = null,
        ?int $associationId = 1
    ): void {
        try {
            $this->auditModel->create([
                'association_id' => $associationId,
                'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
                'user_role' => $userRole ?? ($_SESSION['user_role'] ?? null),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'previous_value' => $previousValue,
                'new_value' => $newValue,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'status' => $status,
                'error_message' => $errorMessage
            ]);
        } catch (\Throwable $e) {
            // Never break core flow because of audit write failure.
            $this->logger->error('Failed to persist audit log', ['error' => $e->getMessage()]);
        }
    }
}

