<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite is required to run this isolated service test.\n");
    exit(1);
}

require_once __DIR__ . '/services/RoleService.php';
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/services/ProductService.php';
require_once __DIR__ . '/services/CartService.php';
require_once __DIR__ . '/services/OrderService.php';
require_once __DIR__ . '/services/ReviewService.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectApiError(callable $action, int $statusCode): void
{
    try {
        $action();
    } catch (ApiException $error) {
        expect($error->statusCode === $statusCode, 'Unexpected API error status.');
        return;
    }

    throw new RuntimeException('Expected an API validation or permission error.');
}

try {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(
        'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE);
         CREATE TABLE users (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             name TEXT NOT NULL,
             email TEXT NOT NULL UNIQUE,
             password_hash TEXT NOT NULL,
             role_id INTEGER NOT NULL
         );
         CREATE TABLE products (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             name TEXT NOT NULL,
             category TEXT NOT NULL,
             price NUMERIC NOT NULL,
             stock INTEGER NOT NULL DEFAULT 0
         );
         CREATE TABLE cart (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             user_id INTEGER NOT NULL,
             product_id INTEGER NOT NULL,
             quantity INTEGER NOT NULL DEFAULT 1,
             UNIQUE (user_id, product_id)
         );
         CREATE TABLE orders (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             user_id INTEGER NOT NULL,
             total NUMERIC NOT NULL,
             status TEXT NOT NULL DEFAULT "pending",
             items_json TEXT NOT NULL,
             created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
         );
         CREATE TABLE reviews (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             user_id INTEGER NOT NULL,
             product_id INTEGER NOT NULL,
             rating INTEGER NOT NULL,
             comment TEXT,
             UNIQUE (user_id, product_id)
         );'
    );

    $admin = ['id' => 1, 'role' => 'admin'];
    $roles = new RoleService($pdo);
    $role = $roles->create(['name' => 'admin'], $admin);
    $customRole = $roles->create(['name' => 'inventory'], $admin);
    $customRole = $roles->update((int) $customRole['id'], ['name' => 'support'], $admin);
    expect($customRole['name'] === 'support', 'Role update failed.');
    $users = new UserService($pdo);
    $user = $users->create([
        'name' => 'Test Customer',
        'email' => 'customer@example.test',
        'password' => 'password123',
        'role_id' => $role['id'],
    ], $admin);
    expect(!isset($user['password_hash']), 'User response exposed a password hash.');
    $customer = ['id' => (int) $user['id'], 'role' => 'customer'];

    $products = new ProductService($pdo);
    $product = $products->create([
        'name' => 'Test Watch',
        'category' => 'Satovi',
        'price' => 19.95,
        'stock' => 3,
    ], $admin);
    expectApiError(
        static fn() => $products->create([
            'name' => 'Invalid Product',
            'category' => 'Other',
            'price' => 1,
        ], $admin),
        400
    );

    $carts = new CartService($pdo);
    $cart = $carts->create([
        'user_id' => 999,
        'product_id' => $product['id'],
        'quantity' => 2,
    ], $customer);
    expect((int) $cart['user_id'] === $customer['id'], 'Customer was able to assign a cart item to another user.');
    expectApiError(static fn() => $carts->get((int) $cart['id'], ['id' => 999, 'role' => 'customer']), 404);

    $orders = new OrderService($pdo);
    expectApiError(
        static fn() => $orders->create([
            'items' => [['product_id' => $product['id'], 'quantity' => 4]],
        ], $customer),
        400
    );
    expect((int) $products->get((int) $product['id'], null)['stock'] === 3, 'Failed order changed product stock.');
    $order = $orders->create([
        'items' => [['product_id' => $product['id'], 'quantity' => 2]],
    ], $customer);
    expect((float) $order['total'] === 39.9, 'Order total was not calculated from the product price.');
    expect((int) $products->get((int) $product['id'], null)['stock'] === 1, 'Order did not decrease product stock.');

    $reviews = new ReviewService($pdo);
    $review = $reviews->create([
        'product_id' => $product['id'],
        'rating' => 5,
        'comment' => 'CRUD service test',
    ], $customer);

    $carts->update((int) $cart['id'], ['quantity' => 4], $customer);
    $orders->update((int) $order['id'], ['status' => 'paid'], $admin);
    $reviews->update((int) $review['id'], ['rating' => 4], $customer);
    $users->update((int) $user['id'], ['name' => 'Updated Customer'], $customer);
    $products->update((int) $product['id'], ['stock' => 5], $admin);

    expect(count($products->list(null)) === 1, 'Product list failed.');
    expect(count($carts->list($customer)) === 1, 'Customer cart list failed.');
    expect(count($orders->list($customer)) === 1, 'Customer order list failed.');
    expect(count($reviews->list(null)) === 1, 'Review list failed.');

    $reviews->delete((int) $review['id'], $customer);
    $orders->delete((int) $order['id'], $admin);
    $carts->delete((int) $cart['id'], $customer);
    $products->delete((int) $product['id'], $admin);
    $users->delete((int) $user['id'], $admin);
    $roles->delete((int) $customRole['id'], $admin);
    $roles->delete((int) $role['id'], $admin);

    echo "Service CRUD, validation, access control, and order-stock tests passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Service test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
