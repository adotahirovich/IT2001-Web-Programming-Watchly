<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/CartDao.php';

final class CartService extends BaseService
{
    public function __construct(PDO $pdo)
    {
        parent::__construct(new CartDao($pdo));
    }

    protected function authorizeList(?array $actor): void
    {
        $this->requireActor($actor);
    }

    protected function authorizeCreate(?array $actor): void
    {
        $this->requireActor($actor);
    }

    protected function authorizeUpdate(array $row, ?array $actor): void
    {
        $this->requireOwnerOrAdmin($row, $actor);
    }

    protected function authorizeDelete(array $row, ?array $actor): void
    {
        $this->requireOwnerOrAdmin($row, $actor);
    }

    protected function canRead(array $row, ?array $actor): bool
    {
        return $actor !== null
            && ($actor['role'] === 'admin' || (int) $actor['id'] === (int) $row['user_id']);
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $actor = $this->requireActor($actor);
        $this->rejectUnknownFields($input, ['user_id', 'product_id', 'quantity']);

        return [
            'user_id' => $actor['role'] === 'admin'
                ? $this->positiveInteger($input, 'user_id')
                : (int) $actor['id'],
            'product_id' => $this->positiveInteger($input, 'product_id'),
            'quantity' => isset($input['quantity'])
                ? $this->positiveInteger($input, 'quantity')
                : 1,
        ];
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $this->rejectUnknownFields($input, ['quantity']);
        return isset($input['quantity'])
            ? ['quantity' => $this->positiveInteger($input, 'quantity')]
            : [];
    }

    private function requireOwnerOrAdmin(array $row, ?array $actor): void
    {
        $actor = $this->requireActor($actor);
        if ($actor['role'] !== 'admin' && (int) $actor['id'] !== (int) $row['user_id']) {
            throw new ApiException('You can only modify your own cart items.', 403);
        }
    }
}
