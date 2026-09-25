<?php
declare(strict_types=1);
namespace App\Tests\Unit\Domain\SalesPoint;
use App\Domain\SalesPoint\Enum\SalesPointType;
use App\Domain\SalesPoint\SalesPoint;
use PHPUnit\Framework\TestCase;
final class SalesPointTest extends TestCase {
 public function testCreatesActivePoint():void{$point=new SalesPoint('POS-01','Bàn A1',SalesPointType::TABLE);self::assertSame('POS-01',$point->getCode());self::assertSame('Bàn A1',$point->getName());self::assertSame(SalesPointType::TABLE,$point->getType());self::assertTrue($point->isActive());}
 public function testUpdateCanDisablePoint():void{$point=new SalesPoint('POS-01','POS 1');$point->update('TABLE-A1','Bàn A1',SalesPointType::TABLE,null,false);self::assertFalse($point->isActive());self::assertSame('TABLE-A1',$point->getCode());}
}
