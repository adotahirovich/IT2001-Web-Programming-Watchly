<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/OrderDao.php';
require_once __DIR__ . '/../dao/ProductDao.php';

final class OrderService extends BaseService
{
    private ProductDao $products;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        parent::__construct(new OrderDao($pdo));
        $this->products = new ProductDao($pdo);
    }

    public function create(array $input, ?array $actor): array
    {
        $this->authorizeCreate($actor);
        $this->pdo->beginTransaction();
        try {
            $data = $this->normalizeCreate($input, $actor);
            $items = json_decode($data['items_json'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($items as $item) {
                if (!$this->products->decreaseStockIfAvailable($item['product_id'], $item['quantity'])) {
                    throw new ApiException(
                        'There is not enough stock for product ' . $item['product_id'] . '.',
                        400
                    );
                }
            }
            $id = $this->dao->create($data);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return $this->get($id, $actor);
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
        $this->requireAdmin($actor);
    }

    protected function authorizeDelete(array $row, ?array $actor): void
    {
        $this->requireAdmin($actor);
    }

    protected function canRead(array $row, ?array $actor): bool
    {
        return $actor !== null
            && ($actor['role'] === 'admin' || (int) $actor['id'] === (int) $row['user_id']);
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $actor = $this->requireActor($actor);
        $allowed = $actor['role'] === 'admin'
            ? ['user_id', 'items', 'status']
            : ['items'];
        $this->rejectUnknownFields($input, $allowed);
        $items = $this->validatedItems($input['items'] ?? null);
        $total = 0.0;

        foreach ($items as &$item) {
            $product = $this->products->findById($item['product_id']);
            if ($product === null) {
                throw new ApiException('Order contains an unknown product.', 400);
            }
            if ((int) $product['stock'] < $item['quantity']) {
                throw new ApiException('There is not enough stock for product ' . $product['id'] . '.', 400);
            }
            $item['name'] = $product['name'];
            $item['unit_price'] = (float) $product['price'];
            $total += $item['quantity'] * (float) $product['price'];
        }
        unset($item);

        $status = $actor['role'] === 'admin'
            ? ($input['status'] ?? 'pending')
            : 'pending';
        if (!is_string($status)) {
            throw new ApiException('Invalid order status.', 400);
        }
        $this->validateStatus($status);

        return [
            'user_id' => $actor['role'] === 'admin'
                ? $this->positiveInteger($input, 'user_id')
                : (int) $actor['id'],
            'total' => number_format($total, 2, '.', ''),
            'status' => $status,
            'items_json' => json_encode($items, JSON_THROW_ON_ERROR),
        ];
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $this->rejectUnknownFields($input, ['status']);
        if (!isset($input['status'])) {
            return [];
        }

        if (!is_string($input['status'])) {
            throw new ApiException('Invalid order status.', 400);
        }
        $status = $input['status'];
        $this->validateStatus($status);
        return ['status' => $status];
    }

    private function validatedItems($items): array
    {
        if (!is_array($items) || $items === []) {
            throw new ApiException('At least one order item is required.', 400);
        }

        $quantities = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ApiException('Each order item must be an object.', 400);
            }
            $this->rejectUnknownFields($item, ['product_id', 'quantity']);
            $productId = $this->positiveInteger($item, 'product_id');
            $quantity = $this->positiveInteger($item, 'quantity');
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        $validated = [];
        foreach ($quantities as $productId => $quantity) {
            $validated[] = ['product_id' => (int) $productId, 'quantity' => $quantity];
        }
        return $validated;
    }

    private function validateStatus(string $status): void
    {
        if (!in_array($status, ['pending', 'paid', 'shipped', 'cancelled'], true)) {
            throw new ApiException('Invalid order status.', 400);
        }
    }
}
