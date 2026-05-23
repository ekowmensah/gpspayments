<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Utils\Logger;
use App\Utils\Request;
use App\Utils\Response;

/**
 * Audit controller for compliance visibility.
 */
class AuditController {
    private Request $request;
    private Logger $logger;
    private AuditLog $auditModel;

    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->auditModel = new AuditLog();
    }

    private function authorize(): void {
        $role = $_SESSION['user_role'] ?? null;
        if (!in_array($role, ['Administrator', 'Auditor'], true)) {
            Response::forbidden();
        }
    }

    public function page(): void {
        $this->authorize();
        Response::view('audit/index', [
            'base_path' => $this->request->basePath()
        ]);
    }

    public function logs(): void {
        $this->authorize();

        $action = $this->request->query('action');
        $userId = $this->request->query('user_id');
        $start = $this->request->query('start');
        $end = $this->request->query('end');

        if (!empty($start) && !empty($end)) {
            $rows = $this->auditModel->getByDateRange((string)$start, (string)$end);
            Response::success(['logs' => $rows, 'count' => count($rows)]);
        }

        if (!empty($action)) {
            $rows = $this->auditModel->getByAction((string)$action);
            Response::success(['logs' => $rows, 'count' => count($rows)]);
        }

        if (!empty($userId) && ctype_digit((string)$userId)) {
            $rows = $this->auditModel->getByUser((int)$userId);
            Response::success(['logs' => $rows, 'count' => count($rows)]);
        }

        $rows = $this->auditModel->orderBy('created_at', 'DESC')->limit(200)->get();
        Response::success(['logs' => $rows, 'count' => count($rows)]);
    }
}

