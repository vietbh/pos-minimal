<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Product\Command\AdjustStock\AdjustStockHandlerEntryPoint;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Product\Command\ChangeLowStockThreshold\ChangeLowStockThresholdHandler;
use App\Application\Product\Command\ChangeLowStockThreshold\ChangeLowStockThresholdInput;
use App\Application\Product\Query\ProductCatalogHandler;
use App\Application\Product\Query\ProductCatalogInput;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Application\Security\Permission;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\User\User;
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
    public function index(Request $request, ProductCatalogHandler $catalog): Response
    {
        $this->denyAccessUnlessGranted(Permission::STOCK_VIEW->value);
        $q = trim((string) $request->query->get('q', ''));
        $pageRaw = $request->query->get('page', 1);
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;
        $result = $catalog(new ProductCatalogInput($q, null, 'name_asc', $page, 50));
        if ($result->page > $result->totalPages && $result->total > 0) {
            $result = $catalog(new ProductCatalogInput($q, null, 'name_asc', $result->totalPages, 50));
        }

        return $this->render('admin/stock/index.html.twig', [
            'products' => $result->items,
            'pagination' => $result,
            'q' => $q,
        ]);
    }

    #[Route('/admin/stock/{productId<\d+>}', name: 'admin_stock_show', methods: ['GET'])]
    public function show(int $productId, ProductRepositoryInterface $products, StockMovementRepositoryInterface $movements): Response
    { $this->denyAccessUnlessGranted(Permission::STOCK_VIEW->value); $product=$products->findById($productId); if($product===null)throw $this->createNotFoundException('Product not found.'); return $this->render('admin/stock/show.html.twig',['product'=>$product,'movements'=>$movements->findByProductId($productId)]); }

    #[Route('/admin/stock/{productId<\d+>}/threshold', name: 'admin_stock_threshold', methods: ['POST'])]
    public function threshold(int $productId, Request $request, ChangeLowStockThresholdHandler $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value);
        if (!$csrf->isTokenValid(new CsrfToken('admin_stock_threshold', (string) $request->request->get('_token', '')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $raw = trim((string) $request->request->get('lowStockThreshold', ''));
        try {
            if ($raw === '' || !ctype_digit($raw)) {
                throw new \InvalidArgumentException('Low stock threshold must be a non-negative integer.');
            }
            $handler(new ChangeLowStockThresholdInput($productId, (int) $raw));
            $this->addFlash('success', 'Low stock threshold updated.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'Unable to update low stock threshold.');
        }

        return $this->redirectToRoute('admin_stock_show', ['productId' => $productId]);
    }

    #[Route('/admin/stock/{productId<\d+>}/adjust', name: 'admin_stock_adjust', methods: ['POST'])]
    public function adjust(
        int $productId,
        Request $request,
        AdjustStockHandlerEntryPoint $handler,
        CsrfTokenManagerInterface $csrf,
        RuntimeActorContextProvider $actorContextProvider,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::STOCK_ADJUST->value);

        if (!$csrf->isTokenValid(new CsrfToken(
            'admin_stock_adjust',
            (string) $request->request->get('_token', ''),
        ))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive() || $user->getId() === null) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }

        $rawQuantityChange = trim((string) $request->request->get('quantityChange', ''));
        if ($rawQuantityChange === '' || !preg_match('/^-?\d+$/', $rawQuantityChange)) {
            $this->addFlash('error', 'Quantity change must be a non-zero integer.');
            return $this->redirectToRoute('admin_stock_show', ['productId' => $productId]);
        }

        $quantityChange = (int) $rawQuantityChange;
        if ($quantityChange === 0) {
            $this->addFlash('error', 'Stock adjustment cannot be zero.');
            return $this->redirectToRoute('admin_stock_show', ['productId' => $productId]);
        }

        $key = trim((string) $request->headers->get(
            'Idempotency-Key',
            $request->request->get('idempotencyKey', ''),
        ));
        if ($key === '') {
            $this->addFlash('error', 'Idempotency key is required.');
            return $this->redirectToRoute('admin_stock_show', ['productId' => $productId]);
        }

        $actorContextProvider->set(new ActorContext(
            $user->getId(),
            null,
            $request->headers->get('X-Request-ID'),
        ));

        try {
            $handler->handle(new AdjustStockInput(
                $productId,
                $quantityChange,
                trim((string) $request->request->get('reason', '')) ?: null,
                $key,
            ));
            $this->addFlash('success', 'Stock updated.');
        } catch (\Throwable $e) {
            $this->addFlash(
                'error',
                $e instanceof \InvalidArgumentException || $e instanceof \DomainException
                    ? $e->getMessage()
                    : 'Unable to adjust stock.',
            );
        } finally {
            $actorContextProvider->clear();
        }

        return $this->redirectToRoute('admin_stock_show', ['productId' => $productId]);
    }
}
