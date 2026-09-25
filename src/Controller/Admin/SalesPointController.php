<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Security\Permission;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\SalesPointGroup;
use App\Domain\SalesPoint\Enum\SalesPointType;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Domain\SalesPoint\Repository\SalesPointGroupRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
#[Route('/admin/sales-points')]
final class SalesPointController extends AbstractController {
 #[Route('',name:'admin_sales_points',methods:['GET','POST'])]
 public function index(Request $request,SalesPointRepositoryInterface $points,SalesPointGroupRepositoryInterface $groups,CsrfTokenManagerInterface $csrf):Response {
  $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
  if($request->isMethod('POST')){
   if(!$csrf->isTokenValid(new CsrfToken('admin_sales_point',(string)$request->request->get('_token','')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
   try{$id=$request->request->getInt('id',0);$point=$id>0?$points->findById($id):null;$groupId=$request->request->getInt('groupId',0);$group=$groupId>0?$groups->findById($groupId):null;$type=SalesPointType::tryFrom(strtoupper(trim((string)$request->request->get('type','POS'))));if($type===null)throw new \InvalidArgumentException('Invalid sales point type.');$code=trim((string)$request->request->get('code',''));$name=trim((string)$request->request->get('name',''));$active=$request->request->getBoolean('isActive');if($point instanceof SalesPoint){$point->update($code,$name,$type,$group,$active);}else{$point=new SalesPoint($code,$name,$type,$group,$active);} $points->save($point);$this->addFlash('success','Sales point saved.');return $this->redirectToRoute('admin_sales_points');}catch(\Throwable $e){$this->addFlash('error',$e instanceof \InvalidArgumentException?$e->getMessage():'Unable to save sales point.');}
  }
  $editId=$request->query->getInt('edit',0);$edit=$editId>0?$points->findById($editId):null;
  return $this->render('admin/sales_points/index.html.twig',['sales_points'=>$points->findAll(),'groups'=>$groups->findAll(),'edit_sales_point'=>$edit instanceof SalesPoint?$edit:null,'csrf_token'=>$csrf->getToken('admin_sales_point')->getValue()]);
 }
 #[Route('/{id<\d+>}/toggle',name:'admin_sales_point_toggle',methods:['POST'])]
 public function toggle(int $id,Request $request,SalesPointRepositoryInterface $points,CsrfTokenManagerInterface $csrf,TranslatorInterface $translator):Response {
  $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
  if(!$csrf->isTokenValid(new CsrfToken('admin_sales_point_toggle',(string)$request->request->get('_token','')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
  $point=$points->findById($id);
  if(!$point instanceof SalesPoint) throw $this->createNotFoundException('Sales point not found.');
  $point->setActive(!$point->isActive());
  $points->save($point);
  $this->addFlash('success',$translator->trans($point->isActive()?'admin.sales_point_enabled':'admin.sales_point_disabled'));
  return $this->redirectToRoute('admin_sales_points');
 }

}
