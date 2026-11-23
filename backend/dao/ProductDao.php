<?php
declare(strict_types=1);

require_once __DIR__ . '/EntityDao.php';

final class ProductDao extends EntityDao
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo, 'products');
    }

    public function decreaseStockIfAvailable(int $productId, int $quantity): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `products` SET `stock` = `stock` - :decrease_by
             WHERE `id` = :id AND `stock` >= :minimum_stock'
        );
        $statement->execute([
            'decrease_by' => $quantity,
            'id' => $productId,
            'minimum_stock' => $quantity,
        ]);

        return $statement->rowCount() === 1;
    }
}
