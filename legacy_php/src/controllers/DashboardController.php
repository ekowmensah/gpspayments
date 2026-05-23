<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Models\Payment;
use App\Models\Member;
use App\Models\CollectionItem;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Logger;

/**
 * Dashboard Controller
 */
class DashboardController {
    private Request $request;
    private Logger $logger;
    private AuthService $authService;
    
    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->authService = new AuthService($logger);
    }
    
    /**
     * Show dashboard
     */
    public function index(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::redirect(APP_URL . '/auth/login');
        }
        
        // Get current user
        $user = $this->authService->currentUser();
        $userRole = $_SESSION['user_role'] ?? null;
        
        // Load role-specific dashboard
        $template = 'dashboard/index';
        
        switch ($userRole) {
            case 'Administrator':
                $data = $this->getAdminDashboard();
                break;
            case 'Treasurer':
                $data = $this->getTreasurerDashboard();
                break;
            case 'Secretary':
                $data = $this->getSecretaryDashboard();
                break;
            case 'Auditor':
                $data = $this->getAuditorDashboard();
                break;
            default:
                $data = $this->getMemberDashboard();
        }
        
        $data['user'] = $user;
        $data['userRole'] = $userRole;
        
        Response::view($template, $data);
    }
    
    /**
     * Get admin dashboard data
     */
    private function getAdminDashboard(): array {
        $paymentModel = new Payment();
        $memberModel = new Member();
        $collectionModel = new CollectionItem();
        $db = db();
        
        $today = date('Y-m-d');
        
        return [
            'title' => 'Admin Dashboard',
            'stats' => [
                'total_members' => $memberModel->count(),
                'active_members' => $memberModel->where('status', '=', 'Active')->count(),
                'active_collections' => $collectionModel->where('status', '=', 'Active')->count(),
                'total_payments' => $paymentModel->count(),
                'todays_payments' => $paymentModel->where('payment_date', '=', $today)->count(),
                'today_amount' => $paymentModel->getDailyTotal($today),
                'pending_verification' => $paymentModel->where('status', '=', 'Pending_Verification')->count(),
                'member_collection_assignments' => $this->countMemberCollectionAssignments($db),
            ],
            'recent_payments' => $paymentModel->orderBy('payment_date', 'DESC')->limit(10)->get(),
            'pending_verification_count' => $paymentModel->where('status', '=', 'Pending_Verification')->count(),
        ];
    }
    
    /**
     * Get treasurer dashboard data
     */
    private function getTreasurerDashboard(): array {
        $paymentModel = new Payment();
        $collectionModel = new CollectionItem();
        $db = db();
        
        $today = date('Y-m-d');
        
        return [
            'title' => 'Treasurer Dashboard',
            'stats' => [
                'today_total' => $paymentModel->getDailyTotal($today),
                'pending_reconciliation' => count($paymentModel->getPendingReconciliation()),
                'unverified_count' => count($paymentModel->getUnverified()),
                'active_collections' => $collectionModel->where('status', '=', 'Active')->count(),
                'member_collection_assignments' => $this->countMemberCollectionAssignments($db),
            ],
            'recent_payments' => $paymentModel->orderBy('payment_date', 'DESC')->limit(10)->get(),
            'pending_reconciliation' => $paymentModel->getPendingReconciliation(),
        ];
    }
    
    /**
     * Get secretary dashboard data
     */
    private function getSecretaryDashboard(): array {
        $memberModel = new Member();
        
        return [
            'title' => 'Secretary Dashboard',
            'stats' => [
                'total_members' => $memberModel->count(),
                'active_members' => count($memberModel->getActive()),
            ],
            'active_members' => $memberModel->getActive(),
        ];
    }
    
    /**
     * Get auditor dashboard data
     */
    private function getAuditorDashboard(): array {
        $paymentModel = new Payment();
        
        return [
            'title' => 'Auditor Dashboard',
            'stats' => [
                'total_payments' => $paymentModel->count(),
                'confirmed' => count($paymentModel->getConfirmed()),
            ],
            'all_payments' => $paymentModel->orderBy('payment_date', 'DESC')->limit(50)->get(),
        ];
    }
    
    /**
     * Get member dashboard data
     */
    private function getMemberDashboard(): array {
        $paymentModel = new Payment();
        
        // Get current member record if exists
        $memberId = $_SESSION['user_id'] ?? null;
        
        return [
            'title' => 'Member Dashboard',
            'my_payments' => $memberId ? $paymentModel->getByMember($memberId) : [],
        ];
    }

    private function countMemberCollectionAssignments(\mysqli $db): int {
        $result = $db->query("SELECT COUNT(*) AS c FROM member_collections WHERE status = 'Active'");
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['c'] ?? 0);
    }
}
?>
