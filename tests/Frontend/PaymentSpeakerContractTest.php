<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PaymentSpeakerContractTest extends TestCase
{
    public function testPosExposesSpeakerControlAndUsesBrowserSpeechSynthesis(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../assets/controllers/pos_checkout_controller.js');
        $template = file_get_contents(__DIR__ . '/../../templates/pos/index.html.twig');

        self::assertIsString($controller);
        self::assertIsString($template);

        self::assertStringContainsString("'speakerButton', 'speakerStatus'", $controller);
        self::assertStringContainsString("'speechSynthesis' in window", $controller);
        self::assertStringContainsString('new SpeechSynthesisUtterance', $controller);
        self::assertStringContainsString('announcePaymentReceived(body.data);', $controller);
        self::assertStringContainsString("data-action=\"click->pos-checkout#toggleSpeaker\"", $template);
        self::assertStringContainsString("data-pos-checkout-target=\"speakerButton\"", $template);
    }

    public function testSpeakerIsPresentationOnlyAndPaymentAmountComesFromAuthoritativeStatusPayload(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../assets/controllers/pos_checkout_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString("const amount = this.formatMajor(data.paidAmount ?? data.amount ?? '0');", $controller);
        self::assertStringContainsString("body.data.status === 'PAID' || body.data.paymentReceived === true", $controller);
        self::assertStringContainsString('this.speak(template.replace(\'%amount%\', amount));', $controller);
    }
}
