<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Payment\Reference;

use App\Application\Payment\Reference\PaymentReferenceNormalizer;
use PHPUnit\Framework\TestCase;

final class PaymentReferenceNormalizerTest extends TestCase
{
    public function testCanonicalReferenceIsPreserved(): void
    {
        $normalizer = new PaymentReferenceNormalizer();

        self::assertSame('A879403A47A9', $normalizer->normalize('A879403A47A9'));
    }

    /** @dataProvider prefixedReferences */
    public function testSupportedDisplayPrefixesAreNormalized(string $input, string $expected): void
    {
        $normalizer = new PaymentReferenceNormalizer();

        self::assertSame($expected, $normalizer->normalize($input));
    }

    /** @return iterable<string,array{0:string,1:string}> */
    public static function prefixedReferences(): iterable
    {
        yield 'pay dash' => ['PAY-A879403A47A9', 'A879403A47A9'];
        yield 'pay underscore' => ['PAY_A879403A47A9', 'A879403A47A9'];
        yield 'pay space' => ['PAY A879403A47A9', 'A879403A47A9'];
        yield 'ref colon' => ['REF: A879403A47A9', 'A879403A47A9'];
        yield 'reference dash' => ['REFERENCE-A879403A47A9', 'A879403A47A9'];
        yield 'lowercase' => [' pay-a879403a47a9 ', 'A879403A47A9'];
    }

    public function testBarePayPrefixIsNotSilentlyRemoved(): void
    {
        $normalizer = new PaymentReferenceNormalizer();

        self::assertSame('PAY12345678', $normalizer->normalize('PAY12345678'));
    }

    public function testInvalidReferenceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PaymentReferenceNormalizer())->normalize('PAY-ABC');
    }
}
