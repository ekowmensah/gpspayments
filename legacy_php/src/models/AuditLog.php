<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Audit Log Model
 */
class AuditLog extends BaseModel {
    protected string $table = 'audit_logs';
    protected array $fillable = [
        'association_id',
        'user_id',
        'user_role',
        'action',
        'entity_type',
        'entity_id',
        'previous_value',
        'new_value',
        'ip_address',
        'user_agent',
        'status',
        'error_message'
    ];
    
    /**
     * Get logs by user
     */
    public function getByUser(int $userId): array {
        return $this->where('user_id', '=', $userId)->orderBy('created_at', 'DESC')->get();
    }
    
    /**
     * Get logs by action
     */
    public function getByAction(string $action): array {
        return $this->where('action', '=', $action)->orderBy('created_at', 'DESC')->get();
    }
    
    /**
     * Get logs for date range
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        $query = "SELECT * FROM {$this->table} WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>
