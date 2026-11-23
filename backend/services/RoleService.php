<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/RoleDao.php';

final class RoleService extends BaseService
{
    public function __construct(PDO $pdo)
    {
        parent::__construct(new RoleDao($pdo));
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $this->rejectUnknownFields($input, ['name']);
        return ['name' => $this->requiredText($input, 'name', 30)];
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $this->rejectUnknownFields($input, ['name']);
        return isset($input['name'])
            ? ['name' => $this->requiredText($input, 'name', 30)]
            : [];
    }
}
