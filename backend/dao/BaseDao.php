<?php
declare(strict_types=1);

abstract class BaseDao
{
    protected PDO $pdo;
    protected string $table;
    protected array $columns;

    protected function __construct(PDO $pdo, string $table, array $columns)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->columns = $columns;
    }

    public function findAll(): array
    {
        return $this->pdo
            ->query("SELECT * FROM `{$this->table}` ORDER BY `id`")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM `{$this->table}` WHERE `id` = :id"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $data = $this->allowedData($data);
        $columns = array_keys($data);
        $quotedColumns = array_map(static fn(string $column): string => "`{$column}`", $columns);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        ));
        $statement->execute(array_combine($placeholders, array_values($data)));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->allowedData($data);
        $assignments = [];
        $values = [];

        foreach ($data as $column => $value) {
            $assignments[] = "`{$column}` = :{$column}";
            $values[$column] = $value;
        }

        $values['id'] = $id;
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE `id` = :id',
            $this->table,
            implode(', ', $assignments)
        ));
        $statement->execute($values);

        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM `{$this->table}` WHERE `id` = :id"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    private function allowedData(array $data): array
    {
        $data = array_intersect_key($data, array_flip($this->columns));
        if ($data === []) {
            throw new InvalidArgumentException('At least one valid field is required.');
        }

        return $data;
    }
}
