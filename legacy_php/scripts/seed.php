<?php
declare(strict_types=1);

/**
 * Seed script:
 * - creates default association
 * - ensures roles exist
 * - creates default admin user
 * - optional demo member + collection item when --with-demo is provided
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

spl_autoload_register(static function($class) {
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

use App\Utils\SecurityHelper;

$db = db();
$withDemo = in_array('--with-demo', $argv ?? [], true);

$db->begin_transaction();

try {
    $roles = [
        'Administrator' => 'Full system access and management',
        'Treasurer' => 'Payment recording and financial operations',
        'Secretary' => 'Member management and records',
        'Auditor' => 'Read-only oversight role',
        'Member' => 'Limited member portal access'
    ];

    foreach ($roles as $name => $description) {
        $stmt = $db->prepare('INSERT INTO roles (name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)');
        $stmt->bind_param('ss', $name, $description);
        $stmt->execute();
    }

    $associationName = 'GPS Payments Demo Association';
    $associationEmail = 'admin@gpspayments.local';
    $associationPhone = '+233000000000';
    $associationAddress = 'Accra, Ghana';

    $stmt = $db->prepare('SELECT id FROM associations WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $associationName);
    $stmt->execute();
    $assoc = $stmt->get_result()->fetch_assoc();

    if (!$assoc) {
        $stmt = $db->prepare('INSERT INTO associations (name, email, phone, address) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $associationName, $associationEmail, $associationPhone, $associationAddress);
        $stmt->execute();
        $associationId = (int)$db->insert_id;
        echo "Created association #{$associationId}\n";
    } else {
        $associationId = (int)$assoc['id'];
        echo "Association exists #{$associationId}\n";
    }

    $roleName = 'Administrator';
    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $roleName);
    $stmt->execute();
    $roleId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);

    if ($roleId === 0) {
        throw new RuntimeException('Administrator role not found.');
    }

    $adminEmail = 'admin@gpspayments.local';
    $adminUser = 'admin';
    $adminPhone = '+233111111111';
    $adminFirstName = 'System';
    $adminLastName = 'Administrator';
    $adminPassword = 'Admin123!';
    $passwordHash = SecurityHelper::hashPassword($adminPassword);

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $existingAdmin = $stmt->get_result()->fetch_assoc();

    if (!$existingAdmin) {
        $stmt = $db->prepare('
            INSERT INTO users (association_id, role_id, username, email, password_hash, first_name, last_name, phone, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Active")
        ');
        $stmt->bind_param('iissssss', $associationId, $roleId, $adminUser, $adminEmail, $passwordHash, $adminFirstName, $adminLastName, $adminPhone);
        $stmt->execute();
        echo "Created default admin user: {$adminEmail}\n";
    } else {
        $adminId = (int)$existingAdmin['id'];
        $stmt = $db->prepare('UPDATE users SET association_id = ?, role_id = ?, username = ?, first_name = ?, last_name = ?, phone = ?, status = "Active" WHERE id = ?');
        $stmt->bind_param('iissssi', $associationId, $roleId, $adminUser, $adminFirstName, $adminLastName, $adminPhone, $adminId);
        $stmt->execute();
        echo "Updated existing admin user #{$adminId}\n";
    }

    if ($withDemo) {
        $demoMemberId = 'MEM-0001';
        $stmt = $db->prepare('SELECT id FROM members WHERE member_id = ? LIMIT 1');
        $stmt->bind_param('s', $demoMemberId);
        $stmt->execute();
        $existingMember = $stmt->get_result()->fetch_assoc();

        if (!$existingMember) {
            $firstName = 'Demo';
            $lastName = 'Member';
            $phone = '+233222222222';
            $email = 'member@example.local';
            $joined = date('Y-m-d');
            $stmt = $db->prepare('
                INSERT INTO members (association_id, member_id, first_name, last_name, phone, email, status, date_joined)
                VALUES (?, ?, ?, ?, ?, ?, "Active", ?)
            ');
            $stmt->bind_param('issssss', $associationId, $demoMemberId, $firstName, $lastName, $phone, $email, $joined);
            $stmt->execute();
            echo "Created demo member {$demoMemberId}\n";
        }

        $collectionName = 'Monthly Dues';
        $stmt = $db->prepare('SELECT id FROM collection_items WHERE association_id = ? AND name = ? LIMIT 1');
        $stmt->bind_param('is', $associationId, $collectionName);
        $stmt->execute();
        $existingItem = $stmt->get_result()->fetch_assoc();

        if (!$existingItem) {
            $amount = 20.00;
            $type = 'Recurring';
            $frequency = 'Monthly';
            $status = 'Active';
            $stmt = $db->prepare('
                INSERT INTO collection_items (association_id, name, amount, type, frequency, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('isdsss', $associationId, $collectionName, $amount, $type, $frequency, $status);
            $stmt->execute();
            echo "Created demo collection item '{$collectionName}'\n";
        }
    }

    $db->commit();

    echo "\nSeeding complete.\n";
    echo "Login email: admin@gpspayments.local\n";
    echo "Login password: Admin123!\n";
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "Seed failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

