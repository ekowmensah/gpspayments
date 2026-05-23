<?php
declare(strict_types=1);

use App\Models\User;
use App\Services\AuthService;
use App\Utils\Logger;
use App\Utils\SecurityHelper;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase {
    public function testRegisterCreatesUserWithHashedPassword(): void {
        $fakeUser = new class extends User {
            public array $created = [];
            private int $nextId = 10;

            public function __construct() {}
            public function findByEmail(string $email): ?array { return null; }
            public function findByUsername(string $username): ?array { return null; }
            public function getRoleIdByName(string $roleName): ?int { return $roleName === 'Administrator' ? 1 : null; }
            public function create(array $data): ?int { $this->created = $data; return $this->nextId++; }
            public function updateLastLogin(int $userId): bool { return true; }
            public function find($id): ?array { return null; }
            public function update(int $id, array $data): bool { return true; }
        };

        $service = new AuthService(new Logger(), $fakeUser);
        $result = $service->register([
            'email' => 'admin@example.com',
            'password' => 'StrongPass1!',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'Administrator'
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($fakeUser->created['password_hash'] ?? null);
        $this->assertTrue(SecurityHelper::verifyPassword('StrongPass1!', $fakeUser->created['password_hash']));
    }

    public function testLoginFailsWithUnknownUser(): void {
        $fakeUser = new class extends User {
            public function __construct() {}
            public function findByEmail(string $email): ?array { return null; }
        };

        $service = new AuthService(new Logger(), $fakeUser);
        $result = $service->login('missing@example.com', 'Password123!');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email or password', $result['message']);
    }
}

