<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/Middleware.php';

final class ExchangeRateService
{
    public function __construct(private array $config, private WatchlyLogger $logger)
    {
    }

    public function convert(string $base, string $quote, float $amount): array
    {
        $base = strtoupper(trim($base));
        $quote = strtoupper(trim($quote));

        if (!preg_match('/^[A-Z]{3}$/', $base) || !preg_match('/^[A-Z]{3}$/', $quote)) {
            throw new ApiException('Currency codes must contain three letters.', 400);
        }
        if (!is_finite($amount) || $amount < 0) {
            throw new ApiException('Amount must be a non-negative number.', 400);
        }

        if ($base === $quote) {
            return [
                'base' => $base,
                'quote' => $quote,
                'amount' => $amount,
                'rate' => 1.0,
                'converted_amount' => $amount,
                'date' => date('Y-m-d'),
            ];
        }

        $baseUrl = rtrim((string) ($this->config['external_api_base'] ?? ''), '/');
        $apiKey = trim((string) ($this->config['external_api_key'] ?? ''));
        if ($baseUrl === '' || $apiKey === '') {
            throw new ApiException('External exchange-rate API URL or API key is not configured.', 500);
        }

        $query = http_build_query([
            'apikey' => $apiKey,
            'base_currency' => $base,
            'currencies' => $quote,
        ]);
        $url = $baseUrl . '/latest?' . $query;
        $timeout = max(1, (int) ($this->config['external_api_timeout'] ?? 5));
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if ($body === false) {
            $this->logger->error(
                'External exchange-rate API request failed; status=' . ($statusLine ?: 'no response')
            );
            throw new ApiException('Exchange-rate service is temporarily unavailable.', 503);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            $this->logger->error('External exchange-rate API returned invalid JSON.');
            throw new ApiException('Exchange-rate service returned an invalid response.', 502);
        }

        if (!is_array($data)) {
            $this->logger->error('External exchange-rate API returned an invalid response structure.');
            throw new ApiException('Exchange-rate service returned an invalid response.', 502);
        }

        if (!preg_match('/\s2\d{2}\s/', $statusLine)) {
            $errorCode = (string) ($data['errors'][0]['code'] ?? $data['message'] ?? 'provider-error');
            $this->logger->error('External exchange-rate API rejected the request; code=' . $errorCode);
            if (preg_match('/currency|base|validation/i', $errorCode)) {
                throw new ApiException('One of the requested currency codes is not supported.', 400);
            }
            if (preg_match('/api.?key|unauthori[sz]ed|invalid.?key/i', $errorCode)) {
                throw new ApiException('External exchange-rate API key is invalid.', 500);
            }
            throw new ApiException('Exchange-rate service is temporarily unavailable.', 503);
        }

        if (
            !isset($data['data'][$quote])
            || !is_numeric($data['data'][$quote])
            || (float) $data['data'][$quote] <= 0
        ) {
            $this->logger->error('External exchange-rate API response is missing the requested rate.');
            throw new ApiException('Exchange-rate service returned an invalid response.', 502);
        }

        $rate = (float) $data['data'][$quote];
        $updatedAt = $data['meta']['last_updated_at'] ?? null;
        $date = is_string($updatedAt) && strtotime($updatedAt) !== false
            ? gmdate('Y-m-d', strtotime($updatedAt))
            : date('Y-m-d');
        $this->logger->info('External exchange-rate request succeeded for ' . $base . ' to ' . $quote . '.');

        return [
            'base' => $base,
            'quote' => $quote,
            'amount' => $amount,
            'rate' => $rate,
            'converted_amount' => round($amount * $rate, 2),
            'date' => $date,
        ];
    }
}
