<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Statistics;

use App\Infrastructure\Statistics\MercureJwtFactory;
use PHPUnit\Framework\TestCase;

final class MercureJwtFactoryTest extends TestCase
{
    public function testItCreatesAValidHs256SubscriberToken(): void
    {
        $secret = str_repeat('s', 40);
        $factory = new MercureJwtFactory($secret);
        $token = $factory->create(['subscribe' => ['http://localhost/statistics/0']], 3600);

        self::assertNotNull($token);
        [$headerPart, $payloadPart, $signaturePart] = explode('.', $token);
        $header = json_decode(self::decode($headerPart), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode(self::decode($payloadPart), true, 512, JSON_THROW_ON_ERROR);
        $expectedSignature = self::encode(hash_hmac('sha256', $headerPart.'.'.$payloadPart, $secret, true));

        self::assertSame('HS256', $header['alg']);
        self::assertSame(['http://localhost/statistics/0'], $payload['mercure']['subscribe']);
        self::assertSame($expectedSignature, $signaturePart);
        self::assertGreaterThan($payload['iat'], $payload['exp']);
    }

    public function testItDisablesTokensWhenNoSecretIsConfigured(): void
    {
        $factory = new MercureJwtFactory('');

        self::assertFalse($factory->isEnabled());
        self::assertNull($factory->create(['subscribe' => ['http://localhost/statistics/0']]));
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
