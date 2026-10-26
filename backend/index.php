<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
require __DIR__ . '/config/bootstrap.php';

use Flight\Engine;

Flight::set('flight.base_url', '');
Flight::set('config', $config);
Flight::set('db', $pdo);
Flight::set('logger', $logger);

require __DIR__ . '/middleware/Middleware.php';
require __DIR__ . '/routes/api.php';

Flight::start();
