<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Collection Item Model
 */
class CollectionItem extends BaseModel {
    protected string $table = 'collection_items';
    protected array $fillable = [
        'association_id',
        'name',
        'description',
        'amount',
        'type',
        'frequency',
        'start_date',
        'due_date',
        'is_required',
        'created_by',
        'status'
    ];

    /**
     * Get active collection items.
     */
    public function getActive(): array {
        return $this->where('status', '=', 'Active')->orderBy('name')->get();
    }

    /**
     * Get collection item with assignment + payment summary.
     */
    public function listWithSummary(?string $status = null): array {
        $query = "
            SELECT
                ci.*,
                COUNT(DISTINCT mc.member_id) AS assigned_members,
                COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) AS total_collected
            FROM collection_items ci
            LEFT JOIN member_collections mc
                ON mc.collection_item_id = ci.id
                AND mc.status = 'Active'
            LEFT JOIN payments p
                ON p.collection_item_id = ci.id
            WHERE (? IS NULL OR ci.status = ?)
            GROUP BY ci.id
            ORDER BY ci.created_at DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ss', $status, $status);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>
