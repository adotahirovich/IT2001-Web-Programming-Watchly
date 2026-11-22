<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/Middleware.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ExchangeRateService.php';
require_once __DIR__ . '/../services/RoleService.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/ProductService.php';
require_once __DIR__ . '/../services/CartService.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/ReviewService.php';

$pdo = Flight::get('db');
$config = Flight::get('config');
$services = [
    'roles' => new RoleService($pdo),
    'users' => new UserService($pdo),
    'products' => new ProductService($pdo),
    'cart' => new CartService($pdo),
    'orders' => new OrderService($pdo),
    'reviews' => new ReviewService($pdo),
];
$authService = new AuthService($pdo, $config);
$exchangeRateService = new ExchangeRateService($config, Flight::get('logger'));

Flight::map('notFound', static function (): void {
    Flight::json(['message' => 'Endpoint not found.'], 404);
});

$handle = static function (callable $action): void {
    try {
        [$status, $payload] = $action();
        Flight::json($payload, $status);
    } catch (ApiException $error) {
        Flight::json(['message' => $error->getMessage()], $error->statusCode);
    } catch (InvalidArgumentException $error) {
        Flight::json(['message' => $error->getMessage()], 400);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            Flight::json(['message' => 'The request conflicts with an existing record or database constraint.'], 409);
            return;
        }
        Flight::get('logger')->error('Database request failed: ' . $error->getMessage());
        Flight::json(['message' => 'An internal database error occurred.'], 500);
    } catch (Throwable $error) {
        Flight::get('logger')->error('API request failed: ' . $error->getMessage());
        Flight::json(['message' => 'An unexpected server error occurred.'], 500);
    }
};

$readBody = static function (): array {
    $raw = file_get_contents('php://input');
    try {
        $object = json_decode($raw === false ? '' : $raw, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new ApiException('Request body must contain valid JSON.', 400);
    }
    if (!$object instanceof stdClass) {
        throw new ApiException('Request body must be a JSON object.', 400);
    }

    return json_decode(json_encode($object, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
};

Flight::route('GET /api/health', static function () use ($handle): void {
    $handle(static fn(): array => [200, ['status' => 'ok']]);
});

Flight::route('GET /api/openapi.yaml', static function (): void {
    $path = __DIR__ . '/../openapi.yaml';
    if (!is_file($path)) {
        Flight::json(['message' => 'OpenAPI document is missing.'], 500);
        return;
    }
    header('Content-Type: application/yaml; charset=utf-8');
    readfile($path);
});

Flight::route('GET /api/docs', static function (): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Watchly API documentation</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script><script>SwaggerUIBundle({url:"./openapi.yaml",dom_id:"#swagger-ui",deepLinking:true,presets:[SwaggerUIBundle.presets.apis],layout:"BaseLayout"});</script></body></html>';
});

Flight::route('POST /api/auth/register', static function () use ($handle, $authService, $readBody): void {
    $handle(static fn(): array => [201, $authService->register($readBody())]);
});

Flight::route('POST /api/auth/login', static function () use ($handle, $authService, $readBody): void {
    $handle(static fn(): array => [200, $authService->login($readBody())]);
});

Flight::route('GET /api/exchange-rate', static function () use ($handle, $exchangeRateService): void {
    $handle(static fn(): array => [200, $exchangeRateService->euroToBam()]);
});

Flight::route('GET /api/@entity', static function (string $entity) use ($handle, $services): void {
    $handle(static function () use ($entity, $services): array {
        if (!isset($services[$entity])) {
            throw new ApiException('Endpoint not found.', 404);
        }
        $actor = AuthMiddleware::user(false);
        return [200, ['data' => $services[$entity]->list($actor)]];
    });
});

Flight::route('GET /api/@entity/@id', static function (string $entity, string $id) use ($handle, $services): void {
    $handle(static function () use ($entity, $id, $services): array {
        if (!isset($services[$entity])) {
            throw new ApiException('Endpoint not found.', 404);
        }
        if (!ctype_digit($id) || (int) $id < 1) {
            throw new ApiException('Record ID must be a positive integer.', 400);
        }
        $actor = AuthMiddleware::user(false);
        return [200, ['data' => $services[$entity]->get((int) $id, $actor)]];
    });
});

Flight::route('POST /api/@entity', static function (string $entity) use ($handle, $services, $readBody): void {
    $handle(static function () use ($entity, $services, $readBody): array {
        if (!isset($services[$entity])) {
            throw new ApiException('Endpoint not found.', 404);
        }
        $actor = AuthMiddleware::user(false);
        return [201, ['data' => $services[$entity]->create($readBody(), $actor)]];
    });
});

foreach (['PUT', 'PATCH'] as $method) {
    Flight::route($method . ' /api/@entity/@id', static function (string $entity, string $id) use ($handle, $services, $readBody): void {
        $handle(static function () use ($entity, $id, $services, $readBody): array {
            if (!isset($services[$entity])) {
                throw new ApiException('Endpoint not found.', 404);
            }
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new ApiException('Record ID must be a positive integer.', 400);
            }
            $actor = AuthMiddleware::user(false);
            return [200, ['data' => $services[$entity]->update((int) $id, $readBody(), $actor)]];
        });
    });
}

Flight::route('DELETE /api/@entity/@id', static function (string $entity, string $id) use ($handle, $services): void {
    $handle(static function () use ($entity, $id, $services): array {
        if (!isset($services[$entity])) {
            throw new ApiException('Endpoint not found.', 404);
        }
        if (!ctype_digit($id) || (int) $id < 1) {
            throw new ApiException('Record ID must be a positive integer.', 400);
        }
        $actor = AuthMiddleware::user(false);
        $services[$entity]->delete((int) $id, $actor);
        return [200, ['message' => 'Record deleted.']];
    });
});