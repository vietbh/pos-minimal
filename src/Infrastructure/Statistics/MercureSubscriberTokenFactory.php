<?php

declare(strict_types=1);

namespace App\Infrastructure\Statistics;

final readonly class MercureSubscriberTokenFactory
{
    public function __construct(
        private MercureJwtFactory $jwtFactory,
        private string $appPublicUrl = 'http://localhost',
    ) {
    }

    public function createForScope(int $scopeId): ?string
    {
        $topic = rtrim($this->appPublicUrl, '/') . '/statistics/' . $scopeId;
        return $this->jwtFactory->create(['subscribe' => [$topic]], 3600);
    }
}
