<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;
use App\Utils\Logger;
use App\Utils\Request;
use App\Utils\Response;

/**
 * Report controller
 */
class ReportController {
    private Request $request;
    private Logger $logger;
    private ReportService $service;

    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->service = new ReportService();
    }

    private function authorize(): void {
        $role = $_SESSION['user_role'] ?? null;
        if (!in_array($role, ['Administrator', 'Treasurer', 'Auditor'], true)) {
            Response::forbidden();
        }
    }

    public function page(): void {
        $this->authorize();
        Response::view('reports/index', [
            'base_path' => $this->request->basePath()
        ]);
    }

    public function daily(): void {
        $this->authorize();
        $date = (string)($this->request->query('date') ?? date('Y-m-d'));
        Response::success($this->service->daily($date));
    }

    public function monthly(): void {
        $this->authorize();
        $year = (int)($this->request->query('year') ?? date('Y'));
        $month = (int)($this->request->query('month') ?? date('m'));
        Response::success($this->service->monthly($year, $month));
    }

    public function arrears(): void {
        $this->authorize();
        $limit = (int)($this->request->query('limit') ?? 200);
        Response::success($this->service->arrears($limit));
    }
}

