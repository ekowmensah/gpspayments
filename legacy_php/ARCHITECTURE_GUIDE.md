# GPS Payments - Architecture & Best Practices Guide

## Table of Contents
1. [Architectural Patterns](#architectural-patterns)
2. [Code Organization](#code-organization)
3. [Design Patterns](#design-patterns)
4. [Best Practices](#best-practices)
5. [Payment Workflow Implementation](#payment-workflow-implementation)
6. [Error Handling Strategy](#error-handling-strategy)

---

## 1. Architectural Patterns

### 1.1 Layered Architecture (MVC-based)

```
┌─────────────────────────────────────┐
│       Views (UI Layer)              │ ← User Interface
├─────────────────────────────────────┤
│   Controllers (Request Handlers)    │ ← Route Handling
├─────────────────────────────────────┤
│   Services (Business Logic)         │ ← Core Logic
├─────────────────────────────────────┤
│   Models (Data Access)              │ ← Database
├─────────────────────────────────────┤
│   Utils & Middleware                │ ← Cross-cutting Concerns
└─────────────────────────────────────┘
```

### 1.2 Payment Flow Architecture

```
Request → Middleware → Controller → Service → Model → Database
                ↓                       ↓
            Validation            Verification
            Auth Check            Reconciliation
            Logging               Notifications
```

---

## 2. Code Organization

### 2.1 Directory Structure
```
src/
├── controllers/
│   ├── AuthController.php         # Authentication (login/logout)
│   ├── MemberController.php       # Member management (CRUD)
│   ├── PaymentController.php      # Payment recording & listing
│   ├── ReconciliationController.php
│   ├── ReportController.php       # Financial reports
│   ├── SettingsController.php     # Admin settings
│   └── BaseController.php         # Base class for all controllers
│
├── models/
│   ├── User.php                   # System users
│   ├── Member.php                 # Members
│   ├── Payment.php                # Payments
│   ├── PaymentVerification.php    # Verification records
│   ├── ReconciliationBatch.php    # Reconciliation batches
│   ├── CollectionItem.php         # Collection items
│   └── BaseModel.php              # Base class with CRUD
│
├── services/
│   ├── AuthService.php            # Authentication logic
│   ├── PaymentService.php         # Payment processing
│   ├── PaymentVerificationService.php
│   ├── NotificationService.php    # SMS/Email
│   ├── ReportService.php          # Report generation
│   ├── ReceiptService.php         # Receipt generation
│   └── ReconciliationService.php
│
├── middleware/
│   ├── AuthMiddleware.php         # User authentication
│   ├── AuthorizationMiddleware.php # Permission checking
│   ├── ValidationMiddleware.php   # Input validation
│   ├── ErrorHandlerMiddleware.php # Exception handling
│   ├── LoggingMiddleware.php      # Request logging
│   └── CsrfMiddleware.php         # CSRF protection
│
├── utils/
│   ├── Validator.php              # Input validation
│   ├── Sanitizer.php              # XSS/SQL prevention
│   ├── Response.php               # JSON responses
│   ├── Logger.php                 # Application logging
│   ├── DateHelper.php             # Date utilities
│   ├── SecurityHelper.php         # Password hashing, tokens
│   ├── FileUploader.php           # File handling
│   └── QRCodeGenerator.php        # QR code generation
│
└── Router.php                     # Request routing

views/
├── layouts/
│   ├── base.php                   # Main template
│   ├── navbar.php
│   └── sidebar.php
├── auth/
│   ├── login.php
│   └── register.php
├── dashboard/
│   ├── admin.php
│   ├── treasurer.php
│   └── member.php
├── payments/
│   ├── record.php                 # Payment entry form
│   ├── verify.php                 # Manual verification
│   ├── reconcile.php              # Cash reconciliation
│   └── list.php                   # Payment history
├── reports/
│   ├── daily.php
│   ├── monthly.php
│   ├── arrears.php
│   └── export.php
└── errors/
    ├── 403.php
    ├── 404.php
    └── 500.php

public/
├── index.php                      # Entry point
├── css/
│   ├── bootstrap.min.css
│   ├── custom.css
│   └── responsive.css
├── js/
│   ├── bootstrap.bundle.min.js
│   ├── custom.js
│   └── ajax-handlers.js
├── assets/
│   ├── images/
│   ├── icons/
│   └── fonts/
└── uploads/
    ├── photos/
    └── receipts/

tests/
├── unit/
│   ├── PaymentVerificationServiceTest.php
│   ├── ValidatorTest.php
│   └── HelperTest.php
├── integration/
│   ├── PaymentFlowTest.php
│   └── ReconciliationTest.php
└── bootstrap.php
```

---

## 3. Design Patterns

### 3.1 Service Layer Pattern
```php
// Controller (thin - only routing)
class PaymentController extends BaseController {
    public function record() {
        $data = $this->request->all();
        $result = $this->paymentService->record($data);
        return $this->response->json($result);
    }
}

// Service (thick - business logic)
class PaymentService {
    public function record($data) {
        // Validate input
        $this->validator->validate($data, $this->rules);
        
        // Create payment
        $payment = $this->paymentModel->create($data);
        
        // Verify payment
        $verification = $this->verificationService->verify($payment);
        
        // If verified, reconcile
        if ($verification->passes()) {
            $this->reconcile($payment);
        }
        
        // Notify member
        $this->notificationService->sendConfirmation($payment);
        
        return $payment;
    }
}
```

**Benefits**:
- Controllers stay thin and readable
- Business logic is testable and reusable
- Services can be swapped without changing controllers

### 3.2 Repository Pattern (Models)
```php
// Abstract repository
abstract class BaseModel {
    protected $table;
    protected $db;
    
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM $this->table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function where($column, $operator, $value) {
        // Build parameterized query
    }
}

// Concrete repository
class PaymentModel extends BaseModel {
    protected $table = 'payments';
    
    public function getUnverified() {
        return $this->where('status', '=', 'Pending_Verification')->get();
    }
    
    public function getByMember($memberId) {
        return $this->where('member_id', '=', $memberId)->orderBy('payment_date', 'DESC')->get();
    }
}
```

### 3.3 Strategy Pattern (Payment Methods)
```php
// Interface for payment strategies
interface PaymentMethodStrategy {
    public function process($payment);
    public function verify($payment);
    public function reconcile($payment);
}

// Concrete strategies
class CashPaymentStrategy implements PaymentMethodStrategy {
    public function process($payment) {
        // Manual entry, mark as Pending_Reconciliation
    }
    
    public function verify($payment) {
        // Run verification checks
    }
    
    public function reconcile($payment) {
        // Manual reconciliation at end of day
    }
}

class MobileMoneyPaymentStrategy implements PaymentMethodStrategy {
    public function process($payment) {
        // Call payment gateway
    }
    
    public function verify($payment) {
        // Auto-verify with provider
    }
    
    public function reconcile($payment) {
        // Auto-reconcile, already done in verify
    }
}

// Factory to select strategy
class PaymentMethodFactory {
    public static function create($method) {
        return match($method) {
            'Cash' => new CashPaymentStrategy(),
            'Mobile Money' => new MobileMoneyPaymentStrategy(),
            'Bank Transfer' => new BankTransferStrategy(),
            default => throw new Exception("Unknown payment method")
        };
    }
}

// Usage
$strategy = PaymentMethodFactory::create($payment['method']);
$strategy->process($payment);
$strategy->verify($payment);
```

### 3.4 Observer Pattern (Events)
```php
// Event class
class PaymentConfirmedEvent {
    public function __construct(public Payment $payment) {}
}

// Observer interfaces
interface EventListener {
    public function handle($event);
}

// Concrete listeners
class SendNotificationListener implements EventListener {
    public function handle(PaymentConfirmedEvent $event) {
        // Send SMS to member
    }
}

class GenerateReceiptListener implements EventListener {
    public function handle(PaymentConfirmedEvent $event) {
        // Generate and store receipt
    }
}

class UpdateArrearsListener implements EventListener {
    public function handle(PaymentConfirmedEvent $event) {
        // Update member arrears
    }
}

// Event bus
class EventBus {
    private $listeners = [];
    
    public function listen($eventClass, $listener) {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }
        $this->listeners[$eventClass][] = $listener;
    }
    
    public function dispatch($event) {
        $eventClass = get_class($event);
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener->handle($event);
        }
    }
}

// Usage
$eventBus->listen(PaymentConfirmedEvent::class, new SendNotificationListener());
$eventBus->listen(PaymentConfirmedEvent::class, new GenerateReceiptListener());
$eventBus->listen(PaymentConfirmedEvent::class, new UpdateArrearsListener());

// When payment is confirmed
$eventBus->dispatch(new PaymentConfirmedEvent($payment));
```

---

## 4. Best Practices

### 4.1 Database Best Practices

#### Use Prepared Statements (ALWAYS)
```php
// ❌ WRONG - SQL Injection vulnerability
$query = "SELECT * FROM members WHERE member_id = '$memberId'";
$result = $db->query($query);

// ✅ CORRECT - Parameterized query
$stmt = $db->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("s", $memberId);
$stmt->execute();
$result = $stmt->get_result();
```

#### Use Transactions for Multi-Step Operations
```php
// Payment processing should be atomic
$db->begin_transaction();
try {
    // Create payment
    $paymentId = $this->paymentModel->create($data);
    
    // Update member arrears
    $this->memberModel->updateArrears($memberId, -$amount);
    
    // Log audit event
    $this->auditModel->log('PAYMENT_CREATED', $paymentId);
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

#### Add Proper Indexes
```sql
-- Already in schema, but remember for custom queries
CREATE INDEX idx_payment_status ON payments(status);
CREATE INDEX idx_member_payment_date ON payments(member_id, payment_date);
CREATE INDEX idx_verification_result ON payment_verifications(verification_result);
```

### 4.2 Code Quality

#### Type Declarations
```php
// ❌ Old style (no types)
function calculateArrears($memberId, $collectionId) {
    // Unclear what types are expected
}

// ✅ New style (with types)
function calculateArrears(int $memberId, int $collectionId): float {
    // Clear what we're expecting and returning
}

// ✅ Using declare(strict_types=1) at top of file
<?php
declare(strict_types=1);

namespace App\Services;

class PaymentService {
    public function recordPayment(int $memberId, float $amount, string $method): array {
        // All parameters must match types exactly
    }
}
```

#### PHPDoc Comments
```php
/**
 * Record a payment for a member
 * 
 * @param int $memberId The member's ID
 * @param float $amount The payment amount
 * @param string $method Payment method (Cash, Mobile Money, etc.)
 * @return array Payment record with status and receipt number
 * @throws PaymentException If payment fails validation
 * @throws DatabaseException If database operation fails
 */
public function recordPayment(int $memberId, float $amount, string $method): array {
    // Implementation
}
```

#### Error Handling
```php
// ❌ Don't suppress errors
$result = @$db->query($sql); // Don't do this!

// ✅ Use try-catch
try {
    $result = $db->query($sql);
} catch (DatabaseException $e) {
    $this->logger->error("Database query failed", ['query' => $sql, 'error' => $e]);
    throw new ApplicationException("Payment processing failed");
}
```

### 4.3 Security Best Practices

#### Input Validation
```php
class PaymentController {
    public function record() {
        // 1. Get input
        $input = $this->request->all();
        
        // 2. Validate (before any processing)
        $rules = [
            'member_id' => 'required|integer|exists:members',
            'amount' => 'required|numeric|min:0.01|max:100000',
            'method' => 'required|in:Cash,Mobile Money,Bank Transfer,USSD,Card',
            'collection_item_id' => 'nullable|integer|exists:collection_items',
        ];
        
        $this->validator->validate($input, $rules);
        
        // 3. Sanitize (if needed)
        $input['amount'] = floatval($input['amount']);
        $input['notes'] = htmlspecialchars($input['notes']);
        
        // 4. Process (safe)
        return $this->paymentService->record($input);
    }
}
```

#### Password Security
```php
// ❌ Never do this
$password_hash = sha1($password);
$password_hash = md5($password);

// ✅ Use modern hashing
$password_hash = password_hash($password, PASSWORD_ARGON2ID);

// ✅ Verify password
if (password_verify($provided_password, $stored_hash)) {
    // Correct password
}
```

#### CSRF Protection
```php
// Generate token in form
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $this->csrfToken(); ?>">
    ...
</form>

// Verify in middleware
class CsrfMiddleware {
    public function handle($request) {
        if ($request->isPost() || $request->isPut() || $request->isDelete()) {
            $token = $request->post('csrf_token');
            if (!$this->verifyToken($token)) {
                throw new CsrfException("Invalid token");
            }
        }
    }
}
```

### 4.4 Logging Best Practices

#### Log Strategically
```php
class PaymentService {
    public function record($data): Payment {
        $this->logger->info('Payment recording started', ['member_id' => $data['member_id']]);
        
        try {
            // 1. Create payment
            $payment = $this->paymentModel->create($data);
            $this->logger->debug('Payment created', ['payment_id' => $payment->id]);
            
            // 2. Verify
            $verification = $this->verificationService->verify($payment);
            if (!$verification->passes()) {
                $this->logger->warning('Payment verification failed', [
                    'payment_id' => $payment->id,
                    'reason' => $verification->failureReason()
                ]);
            }
            
            // 3. Success
            $this->logger->info('Payment recorded successfully', ['payment_id' => $payment->id]);
            return $payment;
            
        } catch (Exception $e) {
            $this->logger->error('Payment recording failed', [
                'error' => $e->getMessage(),
                'data' => $data  // Be careful with sensitive data
            ]);
            throw $e;
        }
    }
}
```

#### Sensitive Data in Logs
```php
// ❌ Don't log full payment details
$this->logger->info("Payment", $payment->toArray());

// ✅ Log only necessary, non-sensitive info
$this->logger->info("Payment received", [
    'payment_id' => $payment->id,
    'member_id' => $payment->member_id,
    'amount' => $payment->amount,  // OK
    // Don't log: phone numbers, full names, card details, etc.
]);
```

---

## 5. Payment Workflow Implementation

### 5.1 Complete Payment Recording Flow

```php
class PaymentService {
    
    public function recordCashPayment(array $data): array {
        $this->logger->info('Attempting to record cash payment', [
            'member_id' => $data['member_id'],
            'amount' => $data['amount']
        ]);
        
        $db = db();
        $db->begin_transaction();
        
        try {
            // Step 1: Create payment record
            $payment = $this->createPayment($data);
            
            // Step 2: Run verification checks
            $verification = $this->verificationService->verify($payment);
            
            // Step 3: Update payment status based on verification
            if ($verification['result'] === 'Pass') {
                $payment['status'] = 'Pending_Reconciliation';
            } else {
                $payment['status'] = 'Pending_Verification'; // Needs manual review
            }
            
            $this->paymentModel->update($payment['id'], $payment);
            
            // Step 4: If verification passed, continue to reconciliation
            if ($verification['result'] === 'Pass') {
                // For cash, wait for reconciliation at end of day
                // But trigger verification event for observers
                $this->eventBus->dispatch(new PaymentVerifiedEvent($payment));
            }
            
            $db->commit();
            
            $this->logger->info('Cash payment recorded successfully', [
                'payment_id' => $payment['id'],
                'status' => $payment['status']
            ]);
            
            return [
                'success' => true,
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'message' => 'Payment recorded. Awaiting reconciliation.'
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $this->logger->error('Cash payment recording failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function recordMobileMoneyPayment(array $data): array {
        try {
            // Step 1: Create payment record
            $payment = $this->createPayment($data);
            
            // Step 2: Verify with mobile money provider
            $provider = $this->getProvider($data['method']);
            $provider_verification = $provider->verify($data['transaction_id']);
            
            if (!$provider_verification['verified']) {
                throw new PaymentException("Provider verification failed");
            }
            
            // Step 3: Run internal verification checks
            $verification = $this->verificationService->verify($payment);
            
            // Step 4: If all checks pass, mark as confirmed
            if ($verification['result'] === 'Pass' && $provider_verification['verified']) {
                $payment['status'] = 'Confirmed';
                $payment['verified_at'] = date('Y-m-d H:i:s');
                $payment['confirmed_at'] = date('Y-m-d H:i:s');
            } else {
                $payment['status'] = 'Pending_Verification';
            }
            
            $this->paymentModel->update($payment['id'], $payment);
            
            // Step 5: If confirmed, trigger events
            if ($payment['status'] === 'Confirmed') {
                $this->eventBus->dispatch(new PaymentConfirmedEvent($payment));
            }
            
            return [
                'success' => true,
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'receipt_number' => $payment['receipt_number'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Mobile money payment failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

### 5.2 Verification Service Implementation

```php
class PaymentVerificationService {
    
    public function verify(array $payment): array {
        $checks = [
            'duplicate' => $this->checkDuplicate($payment),
            'amount' => $this->validateAmount($payment),
            'status' => $this->checkMemberStatus($payment),
            'fraud' => $this->detectFraud($payment)
        ];
        
        // Log verification attempt
        $verification_record = [
            'payment_id' => $payment['id'],
            'duplicate_check' => $checks['duplicate']['passed'],
            'amount_validation' => $checks['amount']['passed'],
            'status_check' => $checks['status']['passed'],
            'fraud_check' => $checks['fraud']['passed'],
            'verification_result' => $this->determineResult($checks),
            'failure_reason' => $this->getFailureReason($checks)
        ];
        
        $this->verificationModel->create($verification_record);
        
        return $verification_record;
    }
    
    private function checkDuplicate(array $payment): array {
        $hours = DUPLICATE_DETECTION_HOURS;
        $since = date('Y-m-d H:i:s', strtotime("-$hours hours"));
        
        $duplicate = $this->paymentModel
            ->where('member_id', '=', $payment['member_id'])
            ->where('collection_item_id', '=', $payment['collection_item_id'])
            ->where('payment_method', '=', $payment['payment_method'])
            ->where('amount', '=', $payment['amount'])
            ->where('created_at', '>', $since)
            ->first();
        
        return [
            'passed' => is_null($duplicate),
            'duplicate' => $duplicate,
            'message' => $duplicate ? 'Duplicate payment detected' : 'No duplicate'
        ];
    }
    
    private function validateAmount(array $payment): array {
        $collection = $this->collectionModel->find($payment['collection_item_id']);
        $expected = $collection['amount'];
        $actual = $payment['amount'];
        
        // Check if overpaid more than threshold
        $overpayment_percent = (($actual - $expected) / $expected) * 100;
        
        $passed = $actual > 0 && $overpayment_percent <= OVERPAYMENT_THRESHOLD_PERCENT;
        
        return [
            'passed' => $passed,
            'expected' => $expected,
            'actual' => $actual,
            'overpayment_percent' => $overpayment_percent,
            'message' => $passed ? 'Amount valid' : 'Amount exceeds threshold'
        ];
    }
    
    private function checkMemberStatus(array $payment): array {
        $member = $this->memberModel->find($payment['member_id']);
        
        return [
            'passed' => $member['status'] === 'Active',
            'member_status' => $member['status'],
            'message' => $member['status'] === 'Active' ? 'Member active' : "Member {$member['status']}"
        ];
    }
    
    private function detectFraud(array $payment): array {
        // Check for burst activity (3+ payments in 60 minutes)
        $minutes = BURST_DETECTION_WINDOW_MINUTES;
        $limit = BURST_DETECTION_LIMIT;
        
        $since = date('Y-m-d H:i:s', strtotime("-$minutes minutes"));
        
        $recent_count = $this->paymentModel
            ->where('member_id', '=', $payment['member_id'])
            ->where('created_at', '>', $since)
            ->count();
        
        return [
            'passed' => $recent_count < $limit,
            'recent_payment_count' => $recent_count,
            'limit' => $limit,
            'message' => $recent_count < $limit ? 'No suspicious activity' : 'Burst activity detected'
        ];
    }
}
```

---

## 6. Error Handling Strategy

### 6.1 Exception Hierarchy
```php
// Base exception
class ApplicationException extends Exception {}

// Specific exceptions
class ValidationException extends ApplicationException {}
class AuthenticationException extends ApplicationException {}
class AuthorizationException extends ApplicationException {}
class PaymentException extends ApplicationException {}
class PaymentVerificationException extends PaymentException {}
class DatabaseException extends ApplicationException {}
class ExternalServiceException extends ApplicationException {}
```

### 6.2 Global Error Handler
```php
// In public/index.php
set_exception_handler(function($exception) {
    $logger = new Logger();
    
    if ($exception instanceof ValidationException) {
        http_response_code(422);
        return json(['error' => $exception->getMessage()], 422);
    }
    
    if ($exception instanceof AuthenticationException) {
        http_response_code(401);
        return json(['error' => 'Unauthorized'], 401);
    }
    
    if ($exception instanceof AuthorizationException) {
        http_response_code(403);
        return json(['error' => 'Forbidden'], 403);
    }
    
    if ($exception instanceof PaymentException) {
        $logger->warning('Payment processing error', ['error' => $exception]);
        http_response_code(400);
        return json(['error' => $exception->getMessage()], 400);
    }
    
    // Default: 500 Internal Server Error
    $logger->error('Unhandled exception', [
        'error' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    http_response_code(500);
    return json(['error' => 'Internal server error'], 500);
});
```

---

## Conclusion

This architecture provides:
- ✅ Clear separation of concerns
- ✅ Testable code
- ✅ Scalable design
- ✅ Security best practices
- ✅ Comprehensive error handling
- ✅ Detailed audit trails

**Follow these patterns consistently across the project for maintainability and quality.**
