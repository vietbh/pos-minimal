<?php
declare(strict_types=1);
namespace App\Domain\SalesPoint;
use App\Domain\SalesPoint\Enum\SalesPointStatus;
use App\Domain\SalesPoint\Enum\SalesPointType;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'sales_points')]
#[ORM\UniqueConstraint(name:'UNIQ_SALES_POINT_CODE', columns:['code'])]
#[ORM\Index(name:'idx_sales_point_active', columns:['status'])]
class SalesPoint {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:'bigint', options:['unsigned'=>true])] private ?int $id=null;
 #[ORM\Column(length:50)] private string $code;
 #[ORM\Column(length:120)] private string $name;
 #[ORM\Column(enumType:SalesPointType::class,length:20)] private SalesPointType $type;
 #[ORM\ManyToOne(targetEntity:SalesPointGroup::class)] #[ORM\JoinColumn(name:'group_id', referencedColumnName:'id', nullable:true, onDelete:'SET NULL')] private ?SalesPointGroup $group;
 #[ORM\Column(enumType:SalesPointStatus::class,length:20)] private SalesPointStatus $status;
 #[ORM\Column(name:'created_at',type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
 #[ORM\Column(name:'updated_at',type:'datetime_immutable')] private \DateTimeImmutable $updatedAt;
 public function __construct(string $code,string $name,SalesPointType $type=SalesPointType::POS,?SalesPointGroup $group=null,bool $active=true){$code=trim($code);$name=trim($name);if($code===''||$name==='')throw new \InvalidArgumentException('Sales point code and name are required.');$this->code=$code;$this->name=$name;$this->type=$type;$this->group=$group;$this->status=$active?SalesPointStatus::ACTIVE:SalesPointStatus::INACTIVE;$this->createdAt=new \DateTimeImmutable();$this->updatedAt=$this->createdAt;}
 public function getId():?int{return $this->id;} public function getCode():string{return $this->code;} public function getName():string{return $this->name;} public function getType():SalesPointType{return $this->type;} public function getGroup():?SalesPointGroup{return $this->group;} public function getStatus():SalesPointStatus{return $this->status;} public function isActive():bool{return $this->status===SalesPointStatus::ACTIVE;} public function setActive(bool $active):void{$this->status=$active?SalesPointStatus::ACTIVE:SalesPointStatus::INACTIVE;$this->updatedAt=new \DateTimeImmutable();}
 public function update(string $code,string $name,SalesPointType $type,?SalesPointGroup $group,bool $active):void{$code=trim($code);$name=trim($name);if($code===''||$name==='')throw new \InvalidArgumentException('Sales point code and name are required.');$this->code=$code;$this->name=$name;$this->type=$type;$this->group=$group;$this->status=$active?SalesPointStatus::ACTIVE:SalesPointStatus::INACTIVE;$this->updatedAt=new \DateTimeImmutable();}
}
