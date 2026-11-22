<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/UserDao.php';

final class UserService extends BaseService
{
    public function __construct(PDO $pdo)
    {
        parent::__construct(new UserDao($pdo));
    }

    protected function authorizeList(?array $actor): void
    {
        $this->requireAdmin($actor);
    }

    protected function authorizeCreate(?array $actor): void
    {
        $this->requireAdmin($actor);
    }

    protected function authorizeUpdate(array $row, ?array $actor): void
    {
        $actor = $this->requireActor($actor);
        if ($actor['role'] !== 'admin' && (int) $actor['id'] !== (int) $row['id']) {
            throw new ApiException('You can only update your own account.', 403);
        }
    }

    protected function authorizeDelete(array $row, ?array $actor): void
    {
        $this->requireAdmin($actor);
    }

    protected function canRead(array $row, ?array $actor): bool
    {
        return $actor !== null
            && ($actor['role'] === 'admin' || (int) $actor['id'] === (int) $row['id']);
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $this->rejectUnknownFields($input, ['name', 'email', 'password', 'role_id']);
        return [
            'name' => $this->requiredText($input, 'name', 100),
            'email' => $this->email($input),
            'password_hash' => $this->passwordHash($input),
            'role_id' => $this->positiveInteger($input, 'role_id'),
        ];
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $actor = $this->requireActor($actor);
        $allowed = $actor['role'] === 'admin'
            ? ['name', 'email', 'password', 'role_id']
            : ['name', 'email', 'password'];
        $this->rejectUnknownFields($input, $allowed);
        $data = [];
        if (array_key_exists('name', $input)) {
            $data['name'] = $this->requiredText($input, 'name', 100);
        }
        if (array_key_exists('email', $input)) {
            $data['email'] = $this->email($input);
        }
        if (array_key_exists('password', $input)) {
            $data['password_hash'] = $this->passwordHash($input);
        }
        if (array_key_exists('role_id', $input)) {
            $data['role_id'] = $this->positiveInteger($input, 'role_id');
        }

        return $data;
    }

    protected function present(array $row): array
    {
        unset($row['password_hash']);
        return $row;
    }

    private function email(array $input): string
    {
        $email = strtolower($this->requiredText($input, 'email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('A valid email address is required.', 400);
        }

        return $email;
    }

    private function passwordHash(array $input): string
    {
        $password = $input['password'] ?? null;
        if (!is_string($password)) {
            throw new ApiException('Password must be a string.', 400);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new ApiException('Password must be between 8 and 72 characters.', 400);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }
}
