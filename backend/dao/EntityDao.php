<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseDao.php';

class EntityDao extends BaseDao
{
    private const ENTITIES = [
        'roles' => [
            'table' => 'roles',
            'columns' => ['name'],
        ],
        'users' => [
            'table' => 'users',
            'columns' => ['name', 'email', 'password_hash', 'role_id'],
        ],
        'products' => [
            'table' => 'products',
            'columns' => ['name', 'category', 'price', 'stock'],
        ],
        'cart' => [
            'table' => 'carts',
            'columns' => ['user_id', 'product_id', 'quantity'],
        ],
        'carts' => [
            'table' => 'carts',
            'columns' => ['user_id', 'product_id', 'quantity'],
        ],
        'orders' => [
            'table' => 'orders',
            'columns' => ['user_id', 'total', 'status', 'items_json'],
        ],
        'reviews' => [
            'table' => 'reviews',
            'columns' => ['user_id', 'product_id', 'rating', 'comment'],
        ],
    ];

    public function __construct(PDO $pdo, string $entity)
    {
        $definition = self::ENTITIES[strtolower($entity)] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported entity: ' . $entity);
        }

        parent::__construct($pdo, $definition['table'], $definition['columns']);
    }
}