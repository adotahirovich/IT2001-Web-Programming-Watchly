<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/ProductDao.php';

final class ProductService extends BaseService
{
    public function __construct(PDO $pdo)
    {
        parent::__construct(new ProductDao($pdo));
    }

    protected function authorizeList(?array $actor): void
    {
    }

    protected function authorizeCreate(?array $actor): void
    {
        $this->requireAdmin($actor);
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
        return true;
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $this->rejectUnknownFields($input, ['name', 'category', 'price', 'stock']);
        $data = [
            'name' => $this->requiredText($input, 'name', 150),
            'category' => $this->category($input),
            'price' => $this->nonNegativeNumber($input, 'price'),
            'stock' => isset($input['stock']) ? $this->nonNegativeInteger($input, 'stock') : 0,
        ];

        return $data;
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $this->rejectUnknownFields($input, ['name', 'category', 'price', 'stock']);
        $data = [];
        if (array_key_exists('name', $input)) {
            $data['name'] = $this->requiredText($input, 'name', 150);
        }
        if (array_key_exists('category', $input)) {
            $data['category'] = $this->category($input);
        }
        if (array_key_exists('price', $input)) {
            $data['price'] = $this->nonNegativeNumber($input, 'price');
        }
        if (array_key_exists('stock', $input)) {
            $data['stock'] = $this->nonNegativeInteger($input, 'stock');
        }

        return $data;
    }

    private function category(array $input): string
    {
        $category = $this->requiredText($input, 'category', 50);
        if (!in_array($category, ['Satovi', 'Nakit'], true)) {
            throw new ApiException('Category must be Satovi or Nakit.', 400);
        }

        return $category;
    }

    private function nonNegativeInteger(array $input, string $field): int
    {
        $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            throw new ApiException($field . ' must be a non-negative integer.', 400);
        }

        return $value;
    }
}
