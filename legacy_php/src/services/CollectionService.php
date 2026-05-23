<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CollectionItem;
use App\Models\Member;
use App\Utils\Logger;

/**
 * Collection setup and assignment service.
 */
class CollectionService {
    private CollectionItem $collectionModel;
    private Member $memberModel;
    private AuditService $auditService;
    private Logger $logger;
    private \mysqli $db;

    public function __construct(Logger $logger) {
        $this->collectionModel = new CollectionItem();
        $this->memberModel = new Member();
        $this->auditService = new AuditService($logger);
        $this->logger = $logger;
        $this->db = db();
    }

    public function listItems(?string $status = null): array {
        return $this->collectionModel->listWithSummary($status);
    }

    public function activeMembers(): array {
        return $this->memberModel->getActive();
    }

    public function activeItems(): array {
        return $this->collectionModel->getActive();
    }

    public function createItem(array $data): array {
        try {
            $itemId = $this->collectionModel->create([
                'association_id' => (int)($data['association_id'] ?? 1),
                'name' => (string)$data['name'],
                'description' => $data['description'] ?? null,
                'amount' => isset($data['amount']) ? (float)$data['amount'] : null,
                'type' => (string)$data['type'],
                'frequency' => (string)$data['frequency'],
                'is_required' => !empty($data['is_required']) ? 1 : 0,
                'start_date' => (string)$data['start_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'Active',
                'created_by' => $data['created_by'] ?? ($_SESSION['user_id'] ?? null),
            ]);

            if (!$itemId) {
                return ['success' => false, 'message' => 'Failed to create collection item'];
            }

            $this->auditService->log(
                action: 'COLLECTION_CREATED',
                entityType: 'CollectionItem',
                entityId: (int)$itemId,
                newValue: json_encode([
                    'name' => $data['name'],
                    'amount' => $data['amount'] ?? null,
                    'type' => $data['type'],
                    'frequency' => $data['frequency'],
                ])
            );

            return [
                'success' => true,
                'message' => 'Collection item created',
                'collection_item_id' => (int)$itemId,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Collection item create failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error creating collection item: ' . $e->getMessage()];
        }
    }

    public function assignToMembers(int $collectionItemId, array $memberIds): array {
        if (empty($memberIds)) {
            return ['success' => false, 'message' => 'No members selected for assignment'];
        }

        $assigned = 0;
        $failed = 0;
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO member_collections (member_id, collection_item_id, assignment_date, status)
            VALUES (?, ?, CURDATE(), 'Active')
        ");

        foreach ($memberIds as $memberId) {
            $memberId = (int)$memberId;
            if ($memberId <= 0) {
                $failed++;
                continue;
            }

            $stmt->bind_param('ii', $memberId, $collectionItemId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $assigned++;
                }
            } else {
                $failed++;
            }
        }

        $this->auditService->log(
            action: 'COLLECTION_ASSIGNED',
            entityType: 'CollectionItem',
            entityId: $collectionItemId,
            newValue: json_encode([
                'mode' => 'selected_members',
                'assigned_count' => $assigned,
                'failed_count' => $failed,
            ])
        );

        return [
            'success' => true,
            'message' => 'Collection assignment completed',
            'assigned_count' => $assigned,
            'failed_count' => $failed,
        ];
    }

    public function assignToAllActiveMembers(int $collectionItemId): array {
        $query = "
            INSERT INTO member_collections (member_id, collection_item_id, assignment_date, status)
            SELECT m.id, ?, CURDATE(), 'Active'
            FROM members m
            LEFT JOIN member_collections mc
                ON mc.member_id = m.id
               AND mc.collection_item_id = ?
            WHERE m.status = 'Active'
              AND mc.id IS NULL
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $collectionItemId, $collectionItemId);
        $ok = $stmt->execute();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to assign collection to active members'];
        }

        $assigned = $stmt->affected_rows;

        $this->auditService->log(
            action: 'COLLECTION_ASSIGNED',
            entityType: 'CollectionItem',
            entityId: $collectionItemId,
            newValue: json_encode([
                'mode' => 'all_active_members',
                'assigned_count' => $assigned,
            ])
        );

        return [
            'success' => true,
            'message' => 'Collection assigned to active members',
            'assigned_count' => $assigned,
        ];
    }

    public function memberStatement(int $memberId): array {
        $member = $this->memberModel->find($memberId);
        if (!$member) {
            return ['success' => false, 'message' => 'Member not found'];
        }

        $query = "
            SELECT
                ci.id AS collection_item_id,
                ci.name AS collection_name,
                ci.type,
                ci.frequency,
                ci.amount,
                mc.assignment_date,
                COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) AS total_paid,
                GREATEST(
                    ci.amount - COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0),
                    0
                ) AS balance
            FROM member_collections mc
            JOIN collection_items ci
                ON ci.id = mc.collection_item_id
            LEFT JOIN payments p
                ON p.member_id = mc.member_id
               AND p.collection_item_id = mc.collection_item_id
            WHERE mc.member_id = ?
              AND mc.status = 'Active'
            GROUP BY
                ci.id, ci.name, ci.type, ci.frequency, ci.amount, mc.assignment_date
            ORDER BY ci.name ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalExpected = 0.0;
        $totalPaid = 0.0;
        $totalBalance = 0.0;
        foreach ($rows as $row) {
            $expected = (float)($row['amount'] ?? 0);
            $paid = (float)($row['total_paid'] ?? 0);
            $balance = (float)($row['balance'] ?? 0);
            $totalExpected += $expected;
            $totalPaid += $paid;
            $totalBalance += $balance;
        }

        return [
            'success' => true,
            'member' => [
                'id' => (int)$member['id'],
                'member_id' => $member['member_id'],
                'full_name' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
                'status' => $member['status'] ?? null,
            ],
            'summary' => [
                'total_expected' => round($totalExpected, 2),
                'total_paid' => round($totalPaid, 2),
                'total_balance' => round($totalBalance, 2),
            ],
            'items' => $rows,
        ];
    }
}
?>

