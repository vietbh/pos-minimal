<?php
declare(strict_types=1);
namespace App\Tests\Unit\Domain\Payment;
use App\Domain\Payment\PaymentBankAccount;
use PHPUnit\Framework\TestCase;
final class PaymentBankAccountWebhookTokenTest extends TestCase {
 public function testTokenIsGeneratedAndCanRotate():void{$account=new PaymentBankAccount('970436','VCB','0123456789','NGUYEN VAN A');$first=$account->getWebhookToken();self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$first);$second=$account->regenerateWebhookToken();self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$second);self::assertNotSame($first,$second);}
}
