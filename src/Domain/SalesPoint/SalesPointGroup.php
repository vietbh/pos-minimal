<?php
declare(strict_types=1);
namespace App\Domain\SalesPoint;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'sales_point_groups')]
#[ORM\UniqueConstraint(name:'UNIQ_SALES_POINT_GROUP_CODE', columns:['code'])]
class SalesPointGroup {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:'bigint', options:['unsigned'=>true])] private ?int $id=null;
 #[ORM\Column(length:50)] private string $code;
 #[ORM\Column(length:120)] private string $name;
 #[ORM\Column(name:'sort_order', type:'integer')] private int $sortOrder;
 #[ORM\Column(length:20)] private string $status='ACTIVE';
 #[ORM\Column(name:'created_at', type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
 #[ORM\Column(name:'updated_at', type:'datetime_immutable')] private \DateTimeImmutable $updatedAt;
 public function __construct(string $code,string $name,int $sortOrder=0){$code=trim($code);$name=trim($name);if($code===''||$name==='')throw new \InvalidArgumentException('Sales point group code and name are required.');$this->code=$code;$this->name=$name;$this->sortOrder=$sortOrder;$this->createdAt=new \DateTimeImmutable();$this->updatedAt=$this->createdAt;}
 public function getId():?int{return $this->id;} public function getCode():string{return $this->code;} public function getName():string{return $this->name;} public function getSortOrder():int{return $this->sortOrder;} public function isActive():bool{return $this->status==='ACTIVE';}
 public function update(string $code,string $name,int $sortOrder,bool $active):void{$code=trim($code);$name=trim($name);if($code===''||$name==='')throw new \InvalidArgumentException('Sales point group code and name are required.');$this->code=$code;$this->name=$name;$this->sortOrder=$sortOrder;$this->status=$active?'ACTIVE':'INACTIVE';$this->updatedAt=new \DateTimeImmutable();}
}
