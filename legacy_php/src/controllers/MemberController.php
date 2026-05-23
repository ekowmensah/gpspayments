<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Member;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\Logger;
use App\Services\AuditService;

/**
 * Member Controller
 */
class MemberController {
    private Member $memberModel;
    private Request $request;
    private Logger $logger;
    private AuditService $auditService;
    
    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->memberModel = new Member();
        $this->auditService = new AuditService($logger);
    }
    
    /**
     * List all members
     */
    public function list(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Secretary' && ($_SESSION['user_role'] ?? null) !== 'Administrator' && ($_SESSION['user_role'] ?? null) !== 'Auditor') {
            Response::forbidden();
        }
        
        $page = (int)$this->request->query('page') ?: 1;
        $status = $this->request->query('status');
        $search = $this->request->query('search');
        
        // Build query
        $query = clone $this->memberModel;
        
        if ($status) {
            $query = $query->where('status', '=', $status);
        }
        
        if ($search) {
            // Search by name or member ID (basic search)
            // Note: This requires custom query method or multiple queries
            $query = $query->where('first_name', 'LIKE', "%$search%");
        }
        
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $listQuery = clone $query;
        $members = $listQuery->orderBy('first_name')->limit(20, ($page - 1) * 20)->get();
        
        Response::success([
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'per_page' => 20
        ]);
    }
    
    /**
     * Show member details
     */
    public function show(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized();
        }
        
        $memberId = (int)$this->request->input('id');
        $member = $this->memberModel->find($memberId);
        
        if (!$member) {
            Response::notFound('Member not found');
        }
        
        Response::success($member);
    }
    
    /**
     * Create new member
     */
    public function create(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Secretary' && ($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        // Validate input
        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'member_id' => 'required|unique:members,member_id',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required|phone',
            'email' => 'email',
            'date_joined' => 'required|date'
        ]);
        
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        
        // Create member
        $memberId = $this->memberModel->create([
            'association_id' => 1,
            'member_id' => $this->request->input('member_id'),
            'first_name' => $this->request->input('first_name'),
            'last_name' => $this->request->input('last_name'),
            'phone' => $this->request->input('phone'),
            'email' => $this->request->input('email'),
            'gender' => $this->request->input('gender'),
            'date_of_birth' => $this->request->input('date_of_birth'),
            'address' => $this->request->input('address'),
            'occupation' => $this->request->input('occupation'),
            'branch_id' => $this->request->input('branch_id'),
            'next_of_kin' => $this->request->input('next_of_kin'),
            'status' => 'Active',
            'date_joined' => $this->request->input('date_joined')
        ]);
        
        if (!$memberId) {
            $this->auditService->log(
                action: 'MEMBER_CREATE_FAILED',
                entityType: 'Member',
                entityId: null,
                status: 'Failed',
                errorMessage: 'Insert operation failed'
            );
            Response::error('Failed to create member');
        }
        
        $this->logger->info('Member created', ['member_id' => $memberId]);
        $this->auditService->log(
            action: 'MEMBER_REGISTERED',
            entityType: 'Member',
            entityId: (int)$memberId,
            newValue: json_encode([
                'member_id' => $this->request->input('member_id'),
                'first_name' => $this->request->input('first_name'),
                'last_name' => $this->request->input('last_name'),
                'phone' => $this->request->input('phone'),
                'status' => 'Active'
            ])
        );
        
        Response::success(['member_id' => $memberId], 'Member created successfully');
    }
    
    /**
     * Update member
     */
    public function update(): void {
        // Check permission
        if (($_SESSION['user_role'] ?? null) !== 'Secretary' && ($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        $memberId = (int)$this->request->input('id');
        $oldData = $this->memberModel->find($memberId);
        
        // Verify member exists
        if (!$oldData) {
            Response::notFound('Member not found');
        }
        
        // Update fields
        $updateData = [];
        
        if ($this->request->has('first_name')) {
            $updateData['first_name'] = $this->request->input('first_name');
        }
        
        if ($this->request->has('last_name')) {
            $updateData['last_name'] = $this->request->input('last_name');
        }
        
        if ($this->request->has('phone')) {
            $updateData['phone'] = $this->request->input('phone');
        }
        
        if ($this->request->has('email')) {
            $updateData['email'] = $this->request->input('email');
        }
        
        if ($this->request->has('address')) {
            $updateData['address'] = $this->request->input('address');
        }
        
        if ($this->request->has('status')) {
            $updateData['status'] = $this->request->input('status');
        }
        
        if (empty($updateData)) {
            Response::error('No fields to update');
        }
        
        $success = $this->memberModel->update($memberId, $updateData);
        
        if (!$success) {
            $this->auditService->log(
                action: 'MEMBER_UPDATE_FAILED',
                entityType: 'Member',
                entityId: $memberId,
                status: 'Failed',
                errorMessage: 'Update operation failed'
            );
            Response::error('Failed to update member');
        }
        
        $this->logger->info('Member updated', ['member_id' => $memberId]);
        $this->auditService->log(
            action: 'MEMBER_UPDATED',
            entityType: 'Member',
            entityId: $memberId,
            previousValue: json_encode($oldData),
            newValue: json_encode($updateData)
        );
        
        Response::success([], 'Member updated successfully');
    }
    
    /**
     * Delete member
     */
    public function delete(): void {
        // Check permission - admin only
        if (($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        $memberId = (int)$this->request->input('id');
        
        // Verify member exists
        $oldData = $this->memberModel->find($memberId);
        if (!$oldData) {
            Response::notFound('Member not found');
        }
        
        $success = $this->memberModel->delete($memberId);
        
        if (!$success) {
            $this->auditService->log(
                action: 'MEMBER_DELETE_FAILED',
                entityType: 'Member',
                entityId: $memberId,
                status: 'Failed',
                errorMessage: 'Delete operation failed'
            );
            Response::error('Failed to delete member');
        }
        
        $this->logger->info('Member deleted', ['member_id' => $memberId]);
        $this->auditService->log(
            action: 'MEMBER_DELETED',
            entityType: 'Member',
            entityId: $memberId,
            previousValue: json_encode($oldData),
            newValue: null
        );
        
        Response::success([], 'Member deleted successfully');
    }
}
?>
