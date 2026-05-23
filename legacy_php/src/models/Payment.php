<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Payment Model
 */
class Payment extends BaseModel {
    protected string $table = 'payments';
    protected array $fillable = [
        'association_id',
        'member_id',
        'collection_item_id',
        'amount',
        'payment_method',
        'payment_date',
        'payment_time',
        'transaction_reference',
        'receipt_number',
        'status',
        'recorded_by',
        'verified_by',
        'verified_at',
        'confirmed_by',
        'confirmed_at',
        'notes'
    ];
    
    /**
     * Get payments by member
     */
    public function getByMember(int $memberId): array {
        return $this->where('member_id', '=', $memberId)->orderBy('payment_date', 'DESC')->get();
    }
    
    /**
     * Get unverified payments
     */
    public function getUnverified(): array {
        return $this->where('status', '=', 'Pending_Verification')->orderBy('created_at')->get();
    }
    
    /**
     * Get pending reconciliation
     */
    public function getPendingReconciliation(): array {
        return $this->where('status', '=', 'Pending_Reconciliation')->where('payment_method', '=', 'Cash')->get();
    }
    
    /**
     * Get confirmed payments
     */
    public function getConfirmed(): array {
        return $this->where('status', '=', 'Confirmed')->orderBy('payment_date', 'DESC')->get();
    }
    
    /**
     * Get payments by date
     */
    public function getByDate(string $date): array {
        return $this->where('payment_date', '=', $date)->orderBy('payment_time')->get();
    }
    
    /**
     * Get payments by method
     */
    public function getByMethod(string $method): array {
        return $this->where('payment_method', '=', $method)->get();
    }
    
    /**
     * Get daily total
     */
    public function getDailyTotal(string $date, string $method = null): float {
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE payment_date = ? AND status = 'Confirmed'";
        $types = 's';
        $params = [$date];
        
        if ($method) {
            $query .= " AND payment_method = ?";
            $types .= 's';
            $params[] = $method;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return (float)($result['total'] ?? 0);
    }
}
?>
