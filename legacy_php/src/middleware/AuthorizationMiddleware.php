<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Response;
use App\Utils\Logger;

/**
 * Authorization Middleware
 * Checks role-based permissions
 */
class AuthorizationMiddleware {
    private Logger $logger;
    
    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        
        $user_role = $_SESSION['user_role'] ?? null;
        
        if (!$user_role) {
            return false;
        }
        
        // Get permissions for role from constants
        $role_permissions = ROLE_PERMISSIONS[$user_role] ?? [];
        
        if (!in_array($permission, $role_permissions)) {
            $this->logger->warning('Permission denied', [
                'user_id' => $_SESSION['user_id'],
                'permission' => $permission,
                'role' => $user_role
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Enforce permission, throw exception if not authorized
     */
    public function enforce(string $permission): void {
        if (!$this->hasPermission($permission)) {
            $this->logger->warning('Unauthorized action', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'permission' => $permission
            ]);
            Response::forbidden('You do not have permission to perform this action');
        }
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool {
        return ($_SESSION['user_role'] ?? null) === 'Administrator';
    }
    
    /**
     * Check if user is treasurer
     */
    public function isTreasurer(): bool {
        return ($_SESSION['user_role'] ?? null) === 'Treasurer';
    }
    
    /**
     * Check if user is secretary
     */
    public function isSecretary(): bool {
        return ($_SESSION['user_role'] ?? null) === 'Secretary';
    }
    
    /**
     * Check if user is auditor
     */
    public function isAuditor(): bool {
        return ($_SESSION['user_role'] ?? null) === 'Auditor';
    }
    
    /**
     * Check if user is member
     */
    public function isMember(): bool {
        return ($_SESSION['user_role'] ?? null) === 'Member';
    }
}
?>
