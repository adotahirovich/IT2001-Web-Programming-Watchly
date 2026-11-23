<?php
declare(strict_types=1);

require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../dao/RoleDao.php';
require_once __DIR__ . '/../middleware/Middleware.php';

final class AuthService
{
    private UserDao $users;
    private RoleDao $roles;

    public function __construct(private PDO $pdo, private array $config)
    {
        $this->users = new UserDao($pdo);
        $this->roles = new RoleDao($pdo);
    }

    public function register(array $input): array
    {
        $this->jwtSecret();
        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        if (!is_string($name) || !is_string($email) || !is_string($password)) {
            throw new ApiException('Name, email, and password must be strings.', 400);
        }
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || strlen($name) > 100) {
            throw new ApiException('Name is required and must be at most 100 characters.', 400);
        }
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('A valid email address is required.', 400);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new ApiException('Password must be between 8 and 72 characters.', 400);
        }

        $customerRole = $this->roles->findByName('customer');
        if ($customerRole === null) {
            throw new RuntimeException('The customer role is missing from the database.');
        }

        $id = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => (int) $customerRole['id'],
        ]);

        return [
            'user' => $this->publicUser($this->users->findById($id)),
            'token' => $this->token($id, 'customer'),
        ];
    }

    public function login(array $input): array
    {
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        if (!is_string($email) || !is_string($password)) {
            throw new ApiException('Email and password must be strings.', 400);
        }
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            throw new ApiException('Email and password are required.', 400);
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new ApiException('Email or password is incorrect.', 401);
        }

        $role = $this->roles->findById((int) $user['role_id']);
        if ($role === null) {
            throw new RuntimeException('The user role is missing from the database.');
        }

        return [
            'user' => $this->publicUser($user),
            'token' => $this->token((int) $user['id'], (string) $role['name']),
        ];
    }

    private function token(int $userId, string $role): string
    {
        $now = time();
        return Firebase\JWT\JWT::encode([
            'iss' => 'watchly',
            'sub' => (string) $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $this->jwtSecret(), 'HS256');
    }

    private function jwtSecret(): string
    {
        $secret = (string) ($this->config['jwt_secret'] ?? '');
        if (strlen($secret) < 32 || $secret === 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET') {
            throw new ApiException('JWT secret is not configured securely.', 500);
        }

        return $secret;
    }

    private function publicUser(?array $user): array
    {
        if ($user === null) {
            throw new RuntimeException('The user record could not be loaded after saving.');
        }

        unset($user['password_hash']);
        return $user;
    }
}
