<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/Middleware.php';
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Nedostaje backend/config/config.php. Kopiraj config.example.php u config.php i podesi bazu.']);
    exit;
}
$config = require $configPath;
date_default_timezone_set('Europe/Sarajevo');

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0775, true);
$logger = new WatchlyLogger($logDir);

try {
    $db = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'] ?? 3306, $db['name'], $db['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    $logger->error('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Nije moguće povezati se s bazom. Provjeri konfiguraciju baze.']);
    exit;
}

require_once __DIR__ . '/../middleware/Middleware.php';
