<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/Middleware.php';
require_once __DIR__ . '/../dao/BaseDao.php';

abstract class BaseService
{
    public function __construct(protected BaseDao $dao)
    {
    }

    public function list(?array $actor): array
    {
        $this->authorizeList($actor);
        $rows = array_filter(
            $this->dao->findAll(),
            fn(array $row): bool => $this->canRead($row, $actor)
        );

        return array_map(fn(array $row): array => $this->present($row), array_values($rows));
    }

    public function get(int $id, ?array $actor): array
    {
        $row = $this->dao->findById($id);
        if ($row === null || !$this->canRead($row, $actor)) {
            throw new ApiException('Record not found.', 404);
        }

        return $this->present($row);
    }

    public function create(array $input, ?array $actor): array
    {
        $this->authorizeCreate($actor);
        $data = $this->normalizeCreate($input, $actor);
        $id = $this->dao->create($data);

        return $this->get($id, $actor);
    }

    public function update(int $id, array $input, ?array $actor): array
    {
        $row = $this->dao->findById($id);
        if ($row === null) {
            throw new ApiException('Record not found.', 404);
        }
        $this->authorizeUpdate($row, $actor);
        $data = $this->normalizeUpdate($input, $actor, $row);
        if ($data === []) {
            throw new ApiException('At least one valid field is required.', 400);
        }

        if (!$this->dao->update($id, $data)) {
            throw new ApiException('Record not found.', 404);
        }

        return $this->get($id, $actor);
    }

    public function delete(int $id, ?array $actor): void
    {
        $row = $this->dao->findById($id);
        if ($row === null) {
            throw new ApiException('Record not found.', 404);
        }
        $this->authorizeDelete($row, $actor);
        if (!$this->dao->delete($id)) {
            throw new ApiException('Record not found.', 404);
        }
    }

    protected function authorizeList(?array $actor): void
    {
        AuthMiddleware::requireAdmin($actor);
    }

    protected function authorizeCreate(?array $actor): void
    {
        AuthMiddleware::requireAdmin($actor);
    }

    protected function authorizeUpdate(array $row, ?array $actor): void
    {
        AuthMiddleware::requireAdmin($actor);
    }

    protected function authorizeDelete(array $row, ?array $actor): void
    {
        AuthMiddleware::requireAdmin($actor);
    }

    protected function canRead(array $row, ?array $actor): bool
    {
        return $actor !== null && $actor['role'] === 'admin';
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        throw new LogicException('Create validation is not implemented.');
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        throw new LogicException('Update validation is not implemented.');
    }

    protected function present(array $row): array
    {
        return $row;
    }

    protected function rejectUnknownFields(array $input, array $allowed): void
    {
        $unknown = array_diff(array_keys($input), $allowed);
        if ($unknown !== []) {
            throw new ApiException('Unsupported field: ' . reset($unknown), 400);
        }
    }

    protected function requiredText(array $input, string $field, int $maxLength): string
    {
        $rawValue = $input[$field] ?? null;
        if (!is_string($rawValue)) {
            throw new ApiException($field . ' must be a string.', 400);
        }
        $value = trim($rawValue);
        if ($value === '' || strlen($value) > $maxLength) {
            throw new ApiException($field . ' is required and must be at most ' . $maxLength . ' characters.', 400);
        }

        return $value;
    }

    protected function nonNegativeNumber(array $input, string $field): float
    {
        if (!isset($input[$field]) || !is_numeric($input[$field]) || (float) $input[$field] < 0) {
            throw new ApiException($field . ' must be a non-negative number.', 400);
        }

        return (float) $input[$field];
    }

    protected function positiveInteger(array $input, string $field): int
    {
        $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new ApiException($field . ' must be a positive integer.', 400);
        }

        return $value;
    }

    protected function requireActor(?array $actor): array
    {
        if ($actor === null) {
            throw new ApiException('Authentication is required.', 401);
        }

        return $actor;
    }

    protected function requireAdmin(?array $actor): void
    {
        AuthMiddleware::requireAdmin($actor);
    }
}
