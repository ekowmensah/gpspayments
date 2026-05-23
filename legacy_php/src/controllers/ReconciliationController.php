<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReconciliationService;
use App\Utils\Logger;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Reconciliation Controller
 */
class ReconciliationController {
    private ReconciliationService $service;
    private Request $request;
    private Logger $logger;

    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->service = new ReconciliationService($logger);
    }

    private function authorize(): void {
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'Administrator' && $role !== 'Treasurer') {
            Response::forbidden();
        }
    }

    public function openBatch(): void {
        $this->authorize();

        $result = $this->service->createBatch([
            'association_id' => 1,
            'reconciliation_type' => $this->request->input('reconciliation_type') ?? 'Cash_End_of_Day',
            'reconciliation_date' => $this->request->input('reconciliation_date') ?? date('Y-m-d'),
            'reconciliation_time' => $this->request->input('reconciliation_time') ?? date('H:i:s'),
            'notes' => $this->request->input('notes'),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function addItem(): void {
        $this->authorize();

        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'batch_id' => 'required|integer',
            'payment_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $result = $this->service->addItemToBatch(
            (int)$this->request->input('batch_id'),
            (int)$this->request->input('payment_id'),
            (string)($this->request->input('action') ?? 'Include'),
            $this->request->input('corrected_amount') !== null ? (float)$this->request->input('corrected_amount') : null,
            $this->request->input('reason')
        );

        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function closeBatch(): void {
        $this->authorize();

        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'batch_id' => 'required|integer',
            'recorded_amount' => 'required|amount'
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $result = $this->service->closeCashBatch(
            (int)$this->request->input('batch_id'),
            (float)$this->request->input('recorded_amount'),
            $this->request->input('notes'),
            (int)($_SESSION['user_id'] ?? 0)
        );

        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function openBatches(): void {
        $this->authorize();
        Response::success(['batches' => $this->service->getOpenBatches()]);
    }
}

