<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Product\Command\AdjustStock\AdjustStockHandlerEntryPoint;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Application\Security\Permission;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class StockController extends AbstractController
{
    #[Route('/admin/stock', name: 'admin_stock_index', methods: ['GET'])]
    public function index(Request $request, SearchProductsHandler $search): Response
    { $this->denyAccessUnlessGranted(Permission::STOCK_VIEW->value); $q=trim((string)$request->query->get('q','')); $products=$q===''?[]:$search(new SearchProductsInput($q,50)); return $this->render('admin/stock/index.html.twig',compact('products','q')); }

    #[Route('/admin/stock/{productId<\d+>}', name: 'admin_stock_show', methods: ['GET'])]
    public function show(int $productId, ProductRepositoryInterface $products, StockMovementRepositoryInterface $movements): Response
    { $this->denyAccessUnlessGranted(Permission::STOCK_VIEW->value); $product=$products->findById($productId); if($product===null)throw $this->createNotFoundException('Product not found.'); return $this->render('admin/stock/show.html.twig',['product'=>$product,'movements'=>$movements->findByProductId($productId)]); }

    #[Route('/admin/stock/{productId<\d+>}/adjust', name: 'admin_stock_adjust', methods: ['POST'])]
    public function adjust(int $productId, Request $request, AdjustStockHandlerEntryPoint $handler, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::STOCK_ADJUST->value); if(!$csrf->isTokenValid(new CsrfToken('admin_stock_adjust',(string)$request->request->get('_token',''))))throw $this->createAccessDeniedException('Invalid CSRF token.'); $key=trim((string)$request->headers->get('Idempotency-Key',$request->request->get('idempotencyKey',''))); if($key===''){throw new \InvalidArgumentException('Idempotency key is required.');} try{$handler->handle(new AdjustStockInput($productId,$request->request->getInt('quantityChange'),trim((string)$request->request->get('reason',''))?:null,$key));$this->addFlash('success','Stock updated.');}catch(\Throwable $e){$this->addFlash('error',$e instanceof \InvalidArgumentException||$e instanceof \DomainException?$e->getMessage():'Unable to adjust stock.');} return $this->redirectToRoute('admin_stock_show',['productId'=>$productId]); }
}
