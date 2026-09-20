<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Security\Permission;
use App\Domain\Product\ProductCategory;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Common\Transaction\TransactionContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
#[Route('/admin/product-categories')]
final class ProductCategoryController extends AbstractController
{
    #[Route('', name: 'admin_product_categories', methods: ['GET'])]
    public function index(ProductCategoryRepositoryInterface $repo): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value); return $this->render('admin/product_category/index.html.twig', ['categories' => $repo->findAllOrdered()]); }

    #[Route('/new', name: 'admin_product_categories_new', methods: ['GET','POST'])]
    public function new(Request $request, ProductCategoryRepositoryInterface $repo, TransactionManagerInterface $tx, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value); $name=trim((string)$request->request->get('name','')); if($request->isMethod('POST')){if(!$csrf->isTokenValid(new CsrfToken('product_category_form',(string)$request->request->get('_token','')))) throw $this->createAccessDeniedException(); try{$tx->run(function(TransactionContextInterface $t)use($name,$repo){if($repo->existsByName($name))throw new \DomainException('A category with this name already exists.'); $repo->save(new ProductCategory($name)); $t->flush();}); $this->addFlash('success','Category created.'); return $this->redirectToRoute('admin_product_categories');}catch(\Throwable $e){$this->addFlash('error',$e instanceof \InvalidArgumentException||$e instanceof \DomainException?$e->getMessage():'Unable to create category.');}} $response=$this->render('admin/product_category/form.html.twig',['name'=>$name,'category'=>null]); if($request->isMethod('POST')){$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);} return $response; }

    #[Route('/{id<\d+>}/edit', name: 'admin_product_categories_edit', methods: ['GET','POST'])]
    public function edit(int $id, Request $request, ProductCategoryRepositoryInterface $repo, TransactionManagerInterface $tx, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value); $category=$repo->findById($id); if(!$category)throw $this->createNotFoundException(); $name=trim((string)$request->request->get('name',$category->getName())); if($request->isMethod('POST')){if(!$csrf->isTokenValid(new CsrfToken('product_category_form',(string)$request->request->get('_token',''))))throw $this->createAccessDeniedException(); try{$tx->run(function(TransactionContextInterface $t)use($category,$name,$repo,$id){if($repo->existsByName($name,$id))throw new \DomainException('A category with this name already exists.'); $category->rename($name);$repo->save($category);$t->flush();});$this->addFlash('success','Category updated.');return $this->redirectToRoute('admin_product_categories');}catch(\Throwable $e){$this->addFlash('error',$e instanceof \InvalidArgumentException||$e instanceof \DomainException?$e->getMessage():'Unable to update category.');}} $response=$this->render('admin/product_category/form.html.twig',['name'=>$name,'category'=>$category]); if($request->isMethod('POST')){$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);} return $response; }

    #[Route('/{id<\d+>}/toggle', name: 'admin_product_categories_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request, ProductCategoryRepositoryInterface $repo, TransactionManagerInterface $tx, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value); if(!$csrf->isTokenValid(new CsrfToken('product_category_state',(string)$request->request->get('_token',''))))throw $this->createAccessDeniedException(); $category=$repo->findById($id);if(!$category)throw $this->createNotFoundException();$tx->run(function(TransactionContextInterface $t)use($category,$repo){$category->isActive()?$category->deactivate():$category->activate();$repo->save($category);$t->flush();});return $this->redirectToRoute('admin_product_categories'); }
}
