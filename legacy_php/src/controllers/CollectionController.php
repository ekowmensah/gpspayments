<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CollectionService;
use App\Utils\Logger;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\SecurityHelper;
use App\Utils\Validator;

/**
 * Collection item setup and assignment controller.
 */
class CollectionController {
    private Request $request;
    private Logger $logger;
    private CollectionService $service;

    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->service = new CollectionService($logger);
    }

    private function requireRoles(array $roles): void {
        $role = $_SESSION['user_role'] ?? null;
        if (!in_array($role, $roles, true)) {
            Response::forbidden();
        }
    }

    public function page(): void {
        $this->requireRoles(['Administrator', 'Treasurer', 'Secretary', 'Auditor']);

        Response::view('collections/index', [
            'base_path' => $this->request->basePath(),
            'csrf_token' => SecurityHelper::getCsrfToken(),
            'collection_types' => COLLECTION_TYPES,
            'collection_frequencies' => COLLECTION_FREQUENCIES,
            'members' => $this->service->activeMembers(),
            'collections' => $this->service->listItems(),
        ]);
    }

    public function list(): void {
        $this->requireRoles(['Administrator', 'Treasurer', 'Secretary', 'Auditor']);
        $status = $this->request->query('status');
        $status = is_string($status) && $status !== '' ? $status : null;
        Response::success([
            'collections' => $this->service->listItems($status),
        ]);
    }

    public function create(): void {
        $this->requireRoles(['Administrator', 'Treasurer']);

        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'name' => 'required',
            'amount' => 'amount',
            'type' => 'required|in:Recurring,One-time,Voluntary',
            'frequency' => 'required|in:Monthly,Quarterly,Yearly,One-time,Custom',
            'start_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $result = $this->service->createItem([
            'association_id' => 1,
            'name' => (string)$this->request->input('name'),
            'description' => $this->request->input('description'),
            'amount' => $this->request->input('amount'),
            'type' => (string)$this->request->input('type'),
            'frequency' => (string)$this->request->input('frequency'),
            'is_required' => $this->request->input('is_required') === '1' || $this->request->input('is_required') === 'on',
            'start_date' => (string)$this->request->input('start_date'),
            'due_date' => $this->request->input('due_date'),
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function assign(): void {
        $this->requireRoles(['Administrator', 'Treasurer', 'Secretary']);

        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'collection_item_id' => 'required|integer',
            'assign_mode' => 'required|in:all,selected',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $collectionItemId = (int)$this->request->input('collection_item_id');
        $mode = (string)$this->request->input('assign_mode');

        if ($mode === 'all') {
            $result = $this->service->assignToAllActiveMembers($collectionItemId);
            Response::json($result, $result['success'] ? 200 : 400);
        }

        $memberIds = $this->request->input('member_ids', []);
        if (!is_array($memberIds)) {
            $memberIds = array_filter(array_map('trim', explode(',', (string)$memberIds)));
        }

        $normalizedIds = [];
        foreach ($memberIds as $memberId) {
            $memberId = (int)$memberId;
            if ($memberId > 0) {
                $normalizedIds[] = $memberId;
            }
        }

        $result = $this->service->assignToMembers($collectionItemId, array_values(array_unique($normalizedIds)));
        Response::json($result, $result['success'] ? 200 : 400);
    }

    public function memberStatement(): void {
        $this->requireRoles(['Administrator', 'Treasurer', 'Secretary', 'Auditor']);

        $memberId = (int)$this->request->query('member_id');
        if ($memberId <= 0) {
            Response::validationError([
                'member_id' => ['member_id is required and must be a valid integer'],
            ]);
        }

        $result = $this->service->memberStatement($memberId);
        Response::json($result, $result['success'] ? 200 : 404);
    }
}
?>

