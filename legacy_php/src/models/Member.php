<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Member Model
 */
class Member extends BaseModel {
    protected string $table = 'members';
    protected array $fillable = [
        'association_id',
        'member_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'address',
        'occupation',
        'branch_id',
        'next_of_kin',
        'passport_photo_path',
        'status',
        'date_joined'
    ];
    
    /**
     * Get active members
     */
    public function getActive(): array {
        return $this->where('status', '=', 'Active')->orderBy('first_name')->get();
    }
    
    /**
     * Find by member ID
     */
    public function findByMemberId(string $memberId): ?array {
        return $this->where('member_id', '=', $memberId)->first();
    }
    
    /**
     * Find by phone
     */
    public function findByPhone(string $phone): ?array {
        return $this->where('phone', '=', $phone)->first();
    }
    
    /**
     * Get members by status
     */
    public function getByStatus(string $status): array {
        return $this->where('status', '=', $status)->get();
    }
}
?>
