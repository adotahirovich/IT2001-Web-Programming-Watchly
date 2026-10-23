<?php
declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class WatchlyLogger {
    public function __construct(private string $directory) {}
    public function info(string $message): void { $this->write('info.log', 'INFO', $message); }
    public function error(string $message): void { $this->write('error.log', 'ERROR', $message); }
    private function write(string $file, string $level, string $message): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $level . ' ' . $message . PHP_EOL;
        file_put_contents($this->directory . '/' . $file, $line, FILE_APPEND | LOCK_EX);
    }
}
function watchly_json(int $status, array $body): void {
    Flight::response()->status($status);
    Flight::json($body);
}
function watchly_user(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) return null;
    try {
        $config = Flight::get('config');
        $decoded = JWT::decode($matches[1], new Key($config['jwt_secret'], 'HS256'));
        return ['id' => (int)$decoded->sub, 'name' => (string)$decoded->name, 'email' => (string)$decoded->email, 'role' => (string)$decoded->role];
    } catch (Throwable $e) {
        return null;
    }
}
function watchly_require_auth(): array {
    $user = watchly_user();
    if (!$user) {
        Flight::get('logger')->error('Rejected request 401 ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
        watchly_json(401, ['message' => 'Potrebna je prijava ili je sesija istekla.']);
        Flight::stop();
    }
    return $user;
}
function watchly_require_admin(): array {
    $user = watchly_require_auth();
    if ($user['role'] !== 'admin') {
        Flight::get('logger')->error('Rejected request 403 user=' . $user['id'] . ' role=' . $user['role']);
        watchly_json(403, ['message' => 'Nemaš dozvolu za ovu akciju.']);
        Flight::stop();
    }
    return $user;
}
Flight::before('start', function () {
    $start = microtime(true);
    Flight::set('request_started_at', $start);
});
Flight::after('start', function () {
    $ms = (int)((microtime(true) - (Flight::get('request_started_at') ?? microtime(true))) * 1000);
    $user = watchly_user();
    Flight::get('logger')->info(($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . ' ' . http_response_code() . ' user=' . ($user['id'] ?? 'guest') . ' role=' . ($user['role'] ?? 'guest') . ' ' . $ms . 'ms');
});
set_exception_handler(function(Throwable $e) {
    if (isset($GLOBALS['logger']) && $GLOBALS['logger'] instanceof WatchlyLogger) $GLOBALS['logger']->error(get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Došlo je do neočekivane greške. Pokušaj ponovo.']);
});
function watchly_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) { watchly_json(400, ['message' => 'Neispravan JSON zahtjev.']); Flight::stop(); }
    return $data;
}
