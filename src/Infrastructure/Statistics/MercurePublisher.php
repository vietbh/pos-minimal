<?php

declare(strict_types=1);

namespace App\Infrastructure\Statistics;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MercurePublisher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MercureJwtFactory $jwtFactory,
        private string $mercurePublishUrl = '',
        private bool $featureEnabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->featureEnabled && trim($this->mercurePublishUrl) !== '' && $this->jwtFactory->isEnabled();
    }

    /** Publishes an invalidation only; the browser must fetch authorized HTTP data. */
    public function publish(string $topic, string $eventId, int $scopeId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $token = $this->jwtFactory->create(['publish' => ['*']], 60);
        if ($token === null) {
            return false;
        }

        $response = $this->httpClient->request('POST', $this->mercurePublishUrl, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'body' => [
                'topic' => $topic,
                'private' => 'on',
                'data' => json_encode([
                    'type' => 'statistics.updated',
                    'eventId' => $eventId,
                    'scopeId' => $scopeId,
                    'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ], JSON_THROW_ON_ERROR),
            ],
            'timeout' => 5,
        ]);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('Mercure publish failed with HTTP %d.', $status));
        }
        return true;
    }
}
