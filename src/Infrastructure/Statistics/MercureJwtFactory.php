<?php

declare(strict_types=1);

namespace App\Infrastructure\Statistics;

/** Minimal HS256 JWT factory for the Mercure Hub's symmetric JWT configuration. */
final readonly class MercureJwtFactory
{
    public function __construct(private string $secret)
    {
    }

    public function isEnabled(): bool
    {
        return trim($this->secret) !== '';
    }

    /** @param array<string,mixed> $mercureClaims */
    public function create(array $mercureClaims, int $ttlSeconds = 60): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $now = time();
        $header = self::base64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'iat' => $now,
            'exp' => $now + max(30, min($ttlSeconds, 3600)),
            'mercure' => $mercureClaims,
        ], JSON_THROW_ON_ERROR));
        $signingInput = $header . '.' . $payload;
        $signature = self::base64Url(hash_hmac('sha256', $signingInput, $this->secret, true));
        return $signingInput . '.' . $signature;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
