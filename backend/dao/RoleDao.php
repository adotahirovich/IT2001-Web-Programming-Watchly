<?php
declare(strict_types=1);

require_once __DIR__ . '/EntityDao.php';

final class RoleDao extends EntityDao
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo, 'roles');
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM `roles` WHERE `name` = :name LIMIT 1'
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
