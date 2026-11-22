<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/Middleware.php';

final class ExchangeRateService
{
    public function __construct(private array $config, private WatchlyLogger $logger)
    {
    }

    public function euroToBam(): array
    {
        $baseUrl = rtrim((string) ($this->config['external_api_base'] ?? ''), '/');
        $apiKey = trim((string) ($this->config['external_api_key'] ?? ''));
        if ($baseUrl === '' || $apiKey === '') {
            throw new ApiException('External API URL or API key is not configured.', 500);
        }

        $url = $baseUrl . '/' . rawurlencode($apiKey) . '/latest/EUR';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(1, (int) ($this->config['external_api_timeout'] ?? 5)),
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($body === false || !preg_match('/\s2\d{2}\s/', $statusLine)) {
            $this->logger->error('External exchange-rate API request failed; status=' . ($statusLine ?: 'no response'));
            throw new ApiException('Exchange-rate service is temporarily unavailable.', 503);
        }

        $data = json_decode($body, true);
        if (
            !is_array($data)
            || ($data['result'] ?? null) !== 'success'
            || !isset($data['time_last_update_utc'], $data['conversion_rates']['BAM'])
            || !is_numeric($data['conversion_rates']['BAM'])
        ) {
            $this->logger->error('External exchange-rate API returned an invalid response.');
            throw new ApiException('Exchange-rate service returned an invalid response.', 502);
        }

        $this->logger->info('External exchange-rate API request succeeded.');
        return [
            'base' => 'EUR',
            'quote' => 'BAM',
            'rate' => (float) $data['conversion_rates']['BAM'],
            'updated_at' => (string) $data['time_last_update_utc'],
        ];
    }
}
