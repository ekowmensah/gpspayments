<?php
/**
 * Database Configuration
 *
 * Connects to MySQL database with error handling
 */

// Load environment variables
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'gpspayments');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);

/**
 * Create database connection
 */
function get_db_connection() {
    try {
        $connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            DB_PORT
        );

        // Check connection
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }

        // Set charset to UTF-8
        $connection->set_charset("utf8mb4");

        return $connection;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Global connection (lazy-loaded)
$GLOBALS['db_connection'] = null;

/**
 * Get singleton database connection
 */
function db() {
    if ($GLOBALS['db_connection'] === null) {
        $GLOBALS['db_connection'] = get_db_connection();
    }
    return $GLOBALS['db_connection'];
}

/**
 * Close database connection
 */
function close_db() {
    if ($GLOBALS['db_connection'] !== null) {
        $GLOBALS['db_connection']->close();
    }
}

// Close connection on script shutdown
register_shutdown_function('close_db');
