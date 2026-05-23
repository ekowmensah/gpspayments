<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentService;
use App\Models\Payment;
use App\Models\Member;
use App\Models\CollectionItem;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\Logger;
use App\Utils\SecurityHelper;
use App\Services\AuditService;

/**
 * Payment Controller
 */
class PaymentController {
    private PaymentService $paymentService;
    private Payment $paymentModel;
    private Member $memberModel;
    private CollectionItem $collectionItemModel;
    private AuditService $auditService;
    private Request $request;
    private Logger $logger;
    
    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->paymentService = new PaymentService($logger);
        $this->paymentModel = new Payment();
        $this->memberModel = new Member();
        $this->collectionItemModel = new CollectionItem();
        $this->auditService = new AuditService($logger);
    }
    
    /**
     * Show payment recording form
     */
    public function showRecordForm(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Treasurer' && ($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        $members = $this->memberModel->getActive();
        $collectionItems = $this->collectionItemModel->getActive();
        
        Response::view('payments/record', [
            'members' => $members,
            'collection_items' => $collectionItems,
            'payment_methods' => PAYMENT_METHODS,
            'base_path' => $this->request->basePath(),
            'csrf_token' => SecurityHelper::getCsrfToken()
        ]);
    }
    
    /**
     * Record cash payment
     */
    public function recordCash(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Treasurer' && ($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        // Validate input
        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'member_id' => 'required|integer',
            'amount' => 'required|amount',
            'payment_date' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        
        // Record payment
        $result = $this->paymentService->recordCashPayment([
            'member_id' => (int)$this->request->input('member_id'),
            'amount' => (float)$this->request->input('amount'),
            'payment_date' => $this->request->input('payment_date'),
            'payment_time' => $this->request->input('payment_time') ?? date('H:i:s'),
            'collection_item_id' => $this->request->input('collection_item_id') ? (int)$this->request->input('collection_item_id') : null,
            'notes' => $this->request->input('notes'),
            'recorded_by' => $_SESSION['user_id'] ?? null
        ]);
        
        $this->logger->info('Cash payment recorded', ['result' => $result]);
        
        Response::json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Record mobile money payment
     */
    public function recordMobileMoneyPayment(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Treasurer' && ($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        // Validate input
        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'member_id' => 'required|integer',
            'amount' => 'required|amount',
            'transaction_id' => 'required',
            'payment_method' => 'required|in:Mobile Money,USSD,Card'
        ]);
        
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        
        // Record payment
        $result = $this->paymentService->recordMobileMoneyPayment([
            'member_id' => (int)$this->request->input('member_id'),
            'amount' => (float)$this->request->input('amount'),
            'payment_method' => $this->request->input('payment_method'),
            'transaction_id' => $this->request->input('transaction_id'),
            'collection_item_id' => $this->request->input('collection_item_id') ? (int)$this->request->input('collection_item_id') : null,
            'notes' => $this->request->input('notes'),
            'recorded_by' => $_SESSION['user_id'] ?? null
        ]);
        
        $this->logger->info('Mobile money payment recorded', ['result' => $result]);
        
        Response::json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Get payment list
     */
    public function list(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized();
        }
        
        $page = (int)$this->request->query('page') ?: 1;
        $status = $this->request->query('status');
        $date = $this->request->query('date');
        $method = $this->request->query('method');
        
        // Build query
        $query = clone $this->paymentModel;
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        if ($date) {
            $query->where('payment_date', '=', $date);
        }
        
        if ($method) {
            $query->where('payment_method', '=', $method);
        }
        
        // Only members see their own payments
        if (($_SESSION['user_role'] ?? null) === 'Member') {
            $query->where('member_id', '=', $_SESSION['user_id']);
        }
        
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $pagedQuery = clone $query;
        $payments = $pagedQuery->orderBy('payment_date', 'DESC')->limit(10, ($page - 1) * 10)->get();
        
        Response::success([
            'payments' => $payments,
            'total' => $total,
            'page' => $page,
            'per_page' => 10
        ]);
    }
    
    /**
     * Get payment details
     */
    public function show(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized();
        }
        
        $paymentId = (int)$this->request->input('id');
        $payment = $this->paymentModel->find($paymentId);
        
        if (!$payment) {
            Response::notFound('Payment not found');
        }
        
        // Only members see their own payments
        if (($_SESSION['user_role'] ?? null) === 'Member' && $payment['member_id'] !== $_SESSION['user_id']) {
            Response::forbidden();
        }
        
        Response::success($payment);
    }
    
    /**
     * Manually verify a payment
     */
    public function verify(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Administrator' && ($_SESSION['user_role'] ?? null) !== 'Treasurer') {
            Response::forbidden();
        }
        
        $paymentId = (int)$this->request->input('payment_id');
        $approved = $this->request->input('approved') === 'true';
        $notes = $this->request->input('notes');
        
        $payment = $this->paymentModel->find($paymentId);
        
        if (!$payment) {
            Response::notFound('Payment not found');
        }
        
        if ($approved) {
            $this->paymentModel->update($paymentId, [
                'status' => 'Verified',
                'verified_by' => $_SESSION['user_id'],
                'verified_at' => date('Y-m-d H:i:s'),
                'notes' => $notes
            ]);
            $this->auditService->log(
                action: 'PAYMENT_VERIFIED',
                entityType: 'Payment',
                entityId: $paymentId,
                previousValue: json_encode(['status' => $payment['status'] ?? null]),
                newValue: json_encode(['status' => 'Verified', 'notes' => $notes])
            );
        } else {
            $this->paymentModel->update($paymentId, [
                'status' => 'Failed',
                'verified_by' => $_SESSION['user_id'],
                'verified_at' => date('Y-m-d H:i:s'),
                'notes' => $notes
            ]);
            $this->auditService->log(
                action: 'PAYMENT_REJECTED',
                entityType: 'Payment',
                entityId: $paymentId,
                previousValue: json_encode(['status' => $payment['status'] ?? null]),
                newValue: json_encode(['status' => 'Failed', 'notes' => $notes])
            );
        }
        
        Response::success([], $approved ? 'Payment verified' : 'Payment rejected');
    }
    
    /**
     * Get daily report
     */
    public function dailyReport(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Treasurer' && ($_SESSION['user_role'] ?? null) !== 'Administrator' && ($_SESSION['user_role'] ?? null) !== 'Auditor') {
            Response::forbidden();
        }
        
        $date = $this->request->input('date') ?? date('Y-m-d');
        
        $report = $this->paymentService->getDailyReport($date);
        
        Response::success($report);
    }
}
?>
