<?php
/**
 * GPS Payments - Application Entry Point
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

/**
 * Load .env file into $_ENV
 */
function load_env_file(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

load_env_file(__DIR__ . '/../.env');

// Load configuration files
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

// Start session early for auth and CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload namespaced classes from src/
spl_autoload_register(function($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    if (count($parts) > 1 && isset($parts[0])) {
        $parts[0] = strtolower($parts[0]);
    }

    $path = __DIR__ . '/../src/' . implode('/', $parts) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

use App\Router;
use App\Utils\Logger;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\SecurityHelper;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\MemberController;
use App\Controllers\PaymentController;
use App\Controllers\CollectionController;
use App\Controllers\ReconciliationController;
use App\Controllers\ReportController;
use App\Controllers\AuditController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$logger = new Logger();

try {
    $request = new Request();
    db();

    // Ensure token exists for forms/pages.
    SecurityHelper::generateCsrfToken();

    $logger->debug('Request received', [
        'method' => $request->method(),
        'path' => $request->path(),
        'ip' => $request->ip()
    ]);

    $router = new Router();

    $basePath = $request->basePath();
    $authMiddleware = new AuthMiddleware($logger);
    $csrfMiddleware = new CsrfMiddleware();

    $requireAuth = static function() use ($authMiddleware): void {
        $authMiddleware->handle();
    };
    $requireCsrf = static function(Request $req) use ($csrfMiddleware): void {
        $csrfMiddleware->handle($req);
    };

    $router->get('/', function() {
        if (!empty($_SESSION['user_id'])) {
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            Response::redirect($basePath . '/dashboard');
        }
        Response::view('auth/login');
    });

    $router->get('/index.php', function() {
        if (!empty($_SESSION['user_id'])) {
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            Response::redirect($basePath . '/dashboard');
        }
        Response::view('auth/login');
    });

    $router->get('/auth/login', function() use ($request, $logger) {
        $controller = new AuthController($request, $logger);
        $controller->showLogin();
    });
    $router->get('/public/auth/login', function() use ($request, $logger) {
        $controller = new AuthController($request, $logger);
        $controller->showLogin();
    });

    $router->post('/auth/login', function() use ($request, $logger, $requireCsrf) {
        $requireCsrf($request);
        $controller = new AuthController($request, $logger);
        $controller->login();
    });
    $router->post('/public/auth/login', function() use ($request, $logger, $requireCsrf) {
        $requireCsrf($request);
        $controller = new AuthController($request, $logger);
        $controller->login();
    });

    $router->get('/auth/logout', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new AuthController($request, $logger);
        $controller->logout();
    });

    $router->get('/dashboard', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new DashboardController($request, $logger);
        $controller->index();
    });

    $router->get('/members/page', function() use ($basePath, $requireAuth) {
        $requireAuth();
        Response::view('members/index', [
            'base_path' => $basePath,
            'csrf_token' => SecurityHelper::getCsrfToken()
        ]);
    });

    $router->get('/members', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new MemberController($request, $logger);
        $controller->list();
    });

    $router->post('/members', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new MemberController($request, $logger);
        $controller->create();
    });

    $router->post('/members/update', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new MemberController($request, $logger);
        $controller->update();
    });

    $router->post('/members/delete', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new MemberController($request, $logger);
        $controller->delete();
    });

    $router->get('/payments/page', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new PaymentController($request, $logger);
        $controller->showRecordForm();
    });

    $router->get('/collections/page', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new CollectionController($request, $logger);
        $controller->page();
    });

    $router->get('/collections', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new CollectionController($request, $logger);
        $controller->list();
    });

    $router->post('/collections', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new CollectionController($request, $logger);
        $controller->create();
    });

    $router->post('/collections/assign', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new CollectionController($request, $logger);
        $controller->assign();
    });

    $router->get('/collections/member-statement', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new CollectionController($request, $logger);
        $controller->memberStatement();
    });

    $router->post('/payments/cash', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new PaymentController($request, $logger);
        $controller->recordCash();
    });

    $router->post('/payments/digital', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new PaymentController($request, $logger);
        $controller->recordMobileMoneyPayment();
    });

    $router->post('/payments/verify', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new PaymentController($request, $logger);
        $controller->verify();
    });

    $router->get('/payments', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new PaymentController($request, $logger);
        $controller->list();
    });

    $router->get('/reports/page', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new ReportController($request, $logger);
        $controller->page();
    });

    $router->get('/reports/daily', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new ReportController($request, $logger);
        $controller->daily();
    });

    $router->get('/reports/monthly', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new ReportController($request, $logger);
        $controller->monthly();
    });

    $router->get('/reports/arrears', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new ReportController($request, $logger);
        $controller->arrears();
    });

    $router->get('/audit/page', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new AuditController($request, $logger);
        $controller->page();
    });

    $router->get('/audit/logs', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new AuditController($request, $logger);
        $controller->logs();
    });

    $router->get('/reconciliation/page', function() use ($request, $requireAuth, $basePath) {
        $requireAuth();
        Response::view('reconciliation/index', [
            'base_path' => $basePath,
            'csrf_token' => SecurityHelper::getCsrfToken()
        ]);
    });

    $router->post('/reconciliation/batches/open', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new ReconciliationController($request, $logger);
        $controller->openBatch();
    });

    $router->post('/reconciliation/batches/add-item', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new ReconciliationController($request, $logger);
        $controller->addItem();
    });

    $router->post('/reconciliation/batches/close', function() use ($request, $logger, $requireAuth, $requireCsrf) {
        $requireAuth();
        $requireCsrf($request);
        $controller = new ReconciliationController($request, $logger);
        $controller->closeBatch();
    });

    $router->get('/reconciliation/batches/open', function() use ($request, $logger, $requireAuth) {
        $requireAuth();
        $controller = new ReconciliationController($request, $logger);
        $controller->openBatches();
    });

    $router->get('/test-db', function() {
        $db = db();
        $result = $db->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
        $row = $result->fetch_assoc();
        Response::success([
            'message' => 'Database connected successfully',
            'tables_found' => (int)($row['table_count'] ?? 0)
        ]);
    });

    if (!$router->dispatch($request->method(), $request->path())) {
        Response::notFound('Route not found: ' . $request->path());
    }
} catch (\Exception $e) {
    $logger->error('Application error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    Response::serverError(APP_ENV === 'development' ? $e->getMessage() : 'Internal server error');
}
