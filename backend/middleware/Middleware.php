<?php
declare(strict_types=1);

use Firebase\JWT\Key;

final class WatchlyLogger
{
    private string $file;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the backend log directory.');
        }

        $this->file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'api.log';
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf(
            "[%s] %s %s%s",
            date(DATE_ATOM),
            $level,
            $message,
            PHP_EOL
        );

        if (file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }
    }
}

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public int $statusCode)
    {
        parent::__construct($message);
    }
}

final class AuthMiddleware
{
    public static function user(bool $required = true): ?array
    {
        $header = self::authorizationHeader();
        if ($header === null || !preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            if ($required) {
                throw new ApiException('Authentication is required.', 401);
            }

            return null;
        }

        $config = Flight::get('config');
        $secret = (string) ($config['jwt_secret'] ?? '');
        if (strlen($secret) < 32 || $secret === 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET') {
            throw new ApiException('JWT secret is not configured securely.', 500);
        }

        try {
            $claims = Firebase\JWT\JWT::decode($matches[1], new Key($secret, 'HS256'));
        } catch (Throwable $error) {
            throw new ApiException('Invalid or expired access token.', 401);
        }

        if (
            ($claims->iss ?? null) !== 'watchly'
            || !isset($claims->sub, $claims->role)
            || !ctype_digit((string) $claims->sub)
            || !is_string($claims->role)
            || $claims->role === ''
        ) {
            throw new ApiException('Invalid access token.', 401);
        }

        return [
            'id' => (int) $claims->sub,
            'role' => (string) $claims->role,
        ];
    }

    public static function requireAdmin(?array $user): void
    {
        if ($user === null) {
            throw new ApiException('Authentication is required.', 401);
        }
        if ($user['role'] !== 'admin') {
            throw new ApiException('Administrator access is required.', 403);
        }
    }

    private static function authorizationHeader(): ?string
    {
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }

        return $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION']
            ?? getenv('HTTP_AUTHORIZATION')
            ?: null;
    }
}
