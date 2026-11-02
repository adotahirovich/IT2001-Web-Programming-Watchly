<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/dao/RoleDao.php';
require_once __DIR__ . '/dao/UserDao.php';
require_once __DIR__ . '/dao/ProductDao.php';
require_once __DIR__ . '/dao/CartDao.php';
require_once __DIR__ . '/dao/OrderDao.php';
require_once __DIR__ . '/dao/ReviewDao.php';

$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Missing backend/config/config.php.\n");
    exit(1);
}

$config = require $configPath;
$db = $config['db'];
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'] ?? 3306,
    $db['name'],
    $db['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->beginTransaction();

    $suffix = bin2hex(random_bytes(5));
    $roleDao = new RoleDao($pdo);
    $userDao = new UserDao($pdo);
    $productDao = new ProductDao($pdo);
    $cartDao = new CartDao($pdo);
    $orderDao = new OrderDao($pdo);
    $reviewDao = new ReviewDao($pdo);

    $roleId = $roleDao->create(['name' => 'dao_test_' . $suffix]);
    $userId = $userDao->create([
        'name' => 'DAO Test',
        'email' => 'dao_' . $suffix . '@example.test',
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'role_id' => $roleId,
    ]);
    $productId = $productDao->create([
        'name' => 'DAO Test Product ' . $suffix,
        'category' => 'Satovi',
        'price' => '19.95',
        'stock' => 3,
    ]);
    $cartId = $cartDao->create([
        'user_id' => $userId,
        'product_id' => $productId,
        'quantity' => 2,
    ]);
    $orderId = $orderDao->create([
        'user_id' => $userId,
        'total' => '39.90',
        'status' => 'pending',
        'items_json' => json_encode([[
            'product_id' => $productId,
            'name' => 'DAO Test Product',
            'quantity' => 2,
            'unit_price' => 19.95,
        ]], JSON_THROW_ON_ERROR),
    ]);
    $reviewId = $reviewDao->create([
        'user_id' => $userId,
        'product_id' => $productId,
        'rating' => 5,
        'comment' => 'CRUD test',
    ]);

    $tests = [
        ['Role', $roleDao, $roleId, ['name' => 'dao_updated_' . $suffix]],
        ['User', $userDao, $userId, ['name' => 'DAO Test Updated']],
        ['Product', $productDao, $productId, ['stock' => 7]],
        ['Cart', $cartDao, $cartId, ['quantity' => 4]],
        ['Order', $orderDao, $orderId, ['status' => 'paid']],
        ['Review', $reviewDao, $reviewId, ['rating' => 4]],
    ];

    foreach ($tests as [$entity, $dao, $id, $changes]) {
        if ($dao->findById($id) === null) {
            throw new RuntimeException($entity . ' read-by-ID failed.');
        }
        if (!$dao->update($id, $changes)) {
            throw new RuntimeException($entity . ' update failed.');
        }
        if ($dao->findById($id) === null || $dao->findAll() === []) {
            throw new RuntimeException($entity . ' read-after-update failed.');
        }
        echo $entity . ": create/read/update OK\n";
    }

    $savedOrderItems = json_decode($orderDao->findById($orderId)['items_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($savedOrderItems) || $savedOrderItems[0]['quantity'] !== 2) {
        throw new RuntimeException('Order items_json read failed.');
    }
    echo "Order items_json: encode/decode OK\n";

    $deletes = [
        ['Review', $reviewDao, $reviewId],
        ['Order', $orderDao, $orderId],
        ['Cart', $cartDao, $cartId],
        ['Product', $productDao, $productId],
        ['User', $userDao, $userId],
        ['Role', $roleDao, $roleId],
    ];

    foreach ($deletes as [$entity, $dao, $id]) {
        if (!$dao->delete($id) || $dao->findById($id) !== null) {
            throw new RuntimeException($entity . ' delete failed.');
        }
        echo $entity . ": delete OK\n";
    }

    $pdo->rollBack();
    echo "Test complete. All test rows were rolled back.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'DAO test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
