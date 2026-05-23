<?php
declare(strict_types=1);

namespace App\Models;

/**
 * User Model
 * Manages system users (admin, treasurer, etc.)
 */
class User extends BaseModel {
    protected string $table = 'users';
    protected array $fillable = [
        'association_id',
        'role_id',
        'username',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'phone',
        'status',
        'last_login'
    ];
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array {
        $query = "
            SELECT u.*, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.email = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: null;
    }
    
    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array {
        $query = "
            SELECT u.*, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.username = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: null;
    }
    
    /**
     * Get active users
     */
    public function getActive(): array {
        $query = "
            SELECT u.*, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.status = 'Active'
            ORDER BY u.first_name ASC
        ";
        $result = $this->db->query($query);

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $userId): bool {
        $now = date('Y-m-d H:i:s');
        return $this->update($userId, ['last_login' => $now]);
    }
    
    /**
     * Get users by role
     */
    public function getByRole(string $role): array {
        $query = "
            SELECT u.*, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.name = ? AND u.status = 'Active'
            ORDER BY u.first_name ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get role ID by role name.
     */
    public function getRoleIdByName(string $roleName): ?int {
        $query = "SELECT id FROM roles WHERE name = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return isset($result['id']) ? (int)$result['id'] : null;
    }
}
?>
