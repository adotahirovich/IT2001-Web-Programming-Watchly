<?php
declare(strict_types=1);

require_once __DIR__ . '/EntityDao.php';

final class UserDao extends EntityDao
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo, 'users');
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM `users` WHERE `email` = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
