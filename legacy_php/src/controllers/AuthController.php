<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\Logger;

/**
 * Authentication Controller
 */
class AuthController {
    private AuthService $authService;
    private Request $request;
    private Logger $logger;
    
    public function __construct(Request $request, Logger $logger) {
        $this->request = $request;
        $this->logger = $logger;
        $this->authService = new AuthService($logger);
    }
    
    /**
     * Show login form
     */
    public function showLogin(): void {
        if ($this->authService->isAuthenticated()) {
            Response::redirect($this->request->basePath() . '/dashboard');
        }
        
        Response::view('auth/login');
    }
    
    /**
     * Process login request
     */
    public function login(): void {
        if (!$this->request->isPost()) {
            Response::redirect($this->request->basePath() . '/auth/login');
        }
        
        // Validate input
        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        if ($validator->fails()) {
            if ($this->request->isAjax()) {
                Response::validationError($validator->errors());
            }
            Response::view('auth/login', ['errors' => $validator->errors()]);
        }
        
        // Attempt login
        $result = $this->authService->login(
            $this->request->input('email'),
            $this->request->input('password')
        );
        
        if (!$result['success']) {
            if ($this->request->isAjax()) {
                Response::error($result['message'], 401);
            }
            Response::view('auth/login', ['error_message' => $result['message']]);
        }
        
        if ($this->request->isAjax()) {
            Response::success($result['user'], 'Login successful');
        }

        Response::redirect($this->request->basePath() . '/dashboard');
    }
    
    /**
     * Logout user
     */
    public function logout(): void {
        $this->authService->logout();
        Response::redirect($this->request->basePath() . '/auth/login');
    }
    
    /**
     * Show registration form (admin only)
     */
    public function showRegister(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized();
        }
        
        // Check if user is admin
        if (($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        Response::view('admin/users/register');
    }
    
    /**
     * Process registration
     */
    public function register(): void {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized();
        }
        
        // Check if user is admin
        if (($_SESSION['user_role'] ?? null) !== 'Administrator') {
            Response::forbidden();
        }
        
        // Validate input
        $validator = new Validator();
        $validator->validate($this->request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'role' => 'required|in:Administrator,Treasurer,Secretary,Auditor,Member'
        ]);
        
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        
        // Attempt registration
        $result = $this->authService->register($this->request->all());
        
        if (!$result['success']) {
            Response::error($result['message']);
        }
        
        Response::success(['user_id' => $result['user_id']], 'User registered successfully');
    }
}
?>
