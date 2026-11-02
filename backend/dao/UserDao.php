<?php
declare(strict_types=1);

require_once __DIR__ . '/EntityDao.php';

final class UserDao extends EntityDao
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo, 'users');
    }
}
