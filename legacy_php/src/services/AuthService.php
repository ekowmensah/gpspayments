<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Utils\SecurityHelper;
use App\Utils\Logger;

/**
 * Authentication Service
 * Handles user login, logout, and session management
 */
class AuthService {
    private User $userModel;
    private Logger $logger;
    
    public function __construct(Logger $logger, ?User $userModel = null) {
        $this->userModel = $userModel ?? new User();
        $this->logger = $logger;
    }
    
    /**
     * Login user with email and password
     */
    public function login(string $email, string $password): array {
        try {
            // Find user by email
            $user = $this->userModel->findByEmail($email);
            
            if (!$user) {
                $this->logger->warning('Login failed: user not found', ['email' => $email]);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Check if user is active
            if ($user['status'] !== 'Active') {
                $this->logger->warning('Login failed: user inactive', ['user_id' => $user['id']]);
                return [
                    'success' => false,
                    'message' => 'Your account has been deactivated'
                ];
            }
            
            // Verify password
            if (!SecurityHelper::verifyPassword($password, $user['password_hash'] ?? '')) {
                $this->logger->warning('Login failed: invalid password', ['email' => $email]);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role_name'] ?? null;
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = SecurityHelper::generateCsrfToken();
            
            // Update last login
            $this->userModel->updateLastLogin($user['id']);
            
            $this->logger->info('User logged in', ['user_id' => $user['id'], 'email' => $email]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $_SESSION['user_name'],
                    'email' => $user['email'],
                    'role' => $_SESSION['user_role']
                ]
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Login error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'An error occurred during login'
            ];
        }
    }
    
    /**
     * Register new user (admin only)
     */
    public function register(array $data): array {
        try {
            // Validate required fields
            if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['role'])) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields'
                ];
            }

            $username = trim((string)($data['username'] ?? ''));
            if ($username === '') {
                $username = strstr((string)$data['email'], '@', true) ?: (string)$data['email'];
            }
            
            // Check if email already exists
            if ($this->userModel->findByEmail($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email already registered'
                ];
            }
            
            // Check password strength
            if (!SecurityHelper::isStrongPassword($data['password'])) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, numbers, and special characters'
                ];
            }
            
            if ($this->userModel->findByUsername($username)) {
                return [
                    'success' => false,
                    'message' => 'Username already taken'
                ];
            }

            $roleId = $this->userModel->getRoleIdByName((string)$data['role']);
            if ($roleId === null) {
                return [
                    'success' => false,
                    'message' => 'Invalid role selected'
                ];
            }

            // Hash password
            $hashedPassword = SecurityHelper::hashPassword($data['password']);
            
            // Create user
            $userId = $this->userModel->create([
                'association_id' => (int)($data['association_id'] ?? 1),
                'role_id' => $roleId,
                'username' => $username,
                'email' => $data['email'],
                'password_hash' => $hashedPassword,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'status' => 'Active'
            ]);
            
            if (!$userId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create user'
                ];
            }
            
            $this->logger->info('New user registered', ['user_id' => $userId, 'email' => $data['email']]);
            
            return [
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => $userId
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Registration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'An error occurred during registration'
            ];
        }
    }
    
    /**
     * Logout user
     */
    public function logout(): void {
        $this->logger->info('User logged out', ['user_id' => $_SESSION['user_id'] ?? null]);
        session_destroy();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool {
        return !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user
     */
    public function currentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->userModel->find($_SESSION['user_id']);
    }
    
    /**
     * Change password
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array {
        try {
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Verify old password
            if (!SecurityHelper::verifyPassword($oldPassword, $user['password_hash'] ?? '')) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Check password strength
            if (!SecurityHelper::isStrongPassword($newPassword)) {
                return [
                    'success' => false,
                    'message' => 'New password does not meet requirements'
                ];
            }
            
            // Update password
            $hashedPassword = SecurityHelper::hashPassword($newPassword);
            
            if ($this->userModel->update($userId, ['password_hash' => $hashedPassword])) {
                $this->logger->info('Password changed', ['user_id' => $userId]);
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to change password'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Change password error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'An error occurred'
            ];
        }
    }
}
?>
