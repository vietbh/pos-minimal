<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Product\Command\ActivateProduct\ActivateProductHandler;
use App\Application\Product\Command\ActivateProduct\ActivateProductInput;
use App\Application\Product\Command\AdjustStock\AdjustStockHandlerEntryPoint;
use App\Application\Product\Command\AdjustStock\AdjustStockInput;
use App\Application\Product\Command\ChangeProductPrice\ChangeProductPriceHandler;
use App\Application\Product\Command\ChangeProductPrice\ChangeProductPriceInput;
use App\Application\Product\Command\CreateProduct\CreateProductHandler;
use App\Application\Product\Command\CreateProduct\CreateProductInput;
use App\Application\Product\Command\DeactivateProduct\DeactivateProductHandler;
use App\Application\Product\Command\DeactivateProduct\DeactivateProductInput;
use App\Application\Product\Command\UpdateProduct\UpdateProductHandler;
use App\Application\Product\Command\UpdateProduct\UpdateProductInput;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Application\Security\Permission;
use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ProductController extends AbstractController
{
    #[Route('/admin/products', name: 'admin_products_index', methods: ['GET'])]
    public function index(Request $request, SearchProductsHandler $search): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_VIEW->value);
        $q = trim((string) $request->query->get('q', ''));
        $products = $q === '' ? [] : $search(new SearchProductsInput($q, 50));

        return $this->render('admin/product/index.html.twig', compact('products', 'q'));
    }

    #[Route('/admin/products/new', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateProductHandler $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_CREATE->value);
        $data = $this->productData($request);
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, $csrf, 'admin_product_form');
            try {
                $id = $handler(new CreateProductInput(
                    name: $data['name'], sellingPrice: Money::fromDecimal($data['sellingPrice']),
                    sku: $data['sku'] !== '' ? new Sku($data['sku']) : null,
                    unit: $data['unit'] !== '' ? $data['unit'] : null,
                    costPrice: $data['costPrice'] !== '' ? Money::fromDecimal($data['costPrice']) : null,
                    lowStockThreshold: $data['lowStockThreshold'], note: $data['note'] !== '' ? $data['note'] : null,
                ));
                $this->addFlash('success', 'Product created.');
                return $this->redirectToRoute('admin_products_show', ['id' => $id]);
            } catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        }
        return $this->render('admin/product/form.html.twig', ['product' => null, 'data' => $data]);
    }

    #[Route('/admin/products/{id<\d+>}', name: 'admin_products_show', methods: ['GET'])]
    public function show(int $id, ProductRepositoryInterface $products, StockMovementRepositoryInterface $movements): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_VIEW->value);
        $product = $products->findById($id);
        if (!$product instanceof Product) throw $this->createNotFoundException('Product not found.');
        return $this->render('admin/product/show.html.twig', ['product' => $product, 'movements' => $movements->findByProductId($id)]);
    }

    #[Route('/admin/products/{id<\d+>}/edit', name: 'admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, ProductRepositoryInterface $products, UpdateProductHandler $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value);
        $product = $products->findById($id);
        if (!$product instanceof Product) throw $this->createNotFoundException('Product not found.');
        $data = $this->productData($request, $product);
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, $csrf, 'admin_product_form');
            try {
                $handler(new UpdateProductInput($id, $data['name'], $data['sku'] !== '' ? new Sku($data['sku']) : null, $data['unit'] !== '' ? $data['unit'] : null, $data['costPrice'] !== '' ? Money::fromDecimal($data['costPrice']) : null, $data['lowStockThreshold'], $data['note'] !== '' ? $data['note'] : null));
                $this->addFlash('success', 'Product updated.');
                return $this->redirectToRoute('admin_products_show', ['id' => $id]);
            } catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        }
        return $this->render('admin/product/form.html.twig', ['product' => $product, 'data' => $data]);
    }

    #[Route('/admin/products/{id<\d+>}/price', name: 'admin_products_price', methods: ['POST'])]
    public function price(int $id, Request $request, ChangeProductPriceHandler $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_PRICE_CHANGE->value);
        $this->checkCsrf($request, $csrf, 'admin_product_price');
        try { $handler(new ChangeProductPriceInput($id, Money::fromDecimal(trim((string) $request->request->get('sellingPrice', ''))))); $this->addFlash('success', 'Price updated.'); }
        catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        return $this->redirectToRoute('admin_products_show', ['id' => $id]);
    }

    #[Route('/admin/products/{id<\d+>}/activate', name: 'admin_products_activate', methods: ['POST'])]
    public function activate(int $id, ActivateProductHandler $handler, Request $request, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_ACTIVATE->value); $this->checkCsrf($request, $csrf, 'admin_product_state'); $handler(new ActivateProductInput($id)); return $this->redirectToRoute('admin_products_show', ['id' => $id]); }

    #[Route('/admin/products/{id<\d+>}/deactivate', name: 'admin_products_deactivate', methods: ['POST'])]
    public function deactivate(int $id, DeactivateProductHandler $handler, Request $request, CsrfTokenManagerInterface $csrf): Response
    { $this->denyAccessUnlessGranted(Permission::PRODUCT_ACTIVATE->value); $this->checkCsrf($request, $csrf, 'admin_product_state'); $handler(new DeactivateProductInput($id)); return $this->redirectToRoute('admin_products_show', ['id' => $id]); }

    #[Route('/admin/products/{id<\d+>}/stock', name: 'admin_products_stock', methods: ['POST'])]
    public function stock(int $id, Request $request, AdjustStockHandlerEntryPoint $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::STOCK_ADJUST->value);
        $this->checkCsrf($request, $csrf, 'admin_stock_adjust');
        $key = trim((string) $request->headers->get('Idempotency-Key', $request->request->get('idempotencyKey', '')));
        if ($key === '') { $this->addFlash('error', 'Idempotency key is required.'); return $this->redirectToRoute('admin_products_show', ['id' => $id]); }
        try { $handler->handle(new AdjustStockInput($id, $request->request->getInt('quantityChange'), trim((string) $request->request->get('reason', '')) ?: null, $key)); $this->addFlash('success', 'Stock updated.'); }
        catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        return $this->redirectToRoute('admin_products_show', ['id' => $id]);
    }

    /** @return array{name:string,sellingPrice:string,sku:string,unit:string,costPrice:string,lowStockThreshold:int,note:string} */
    private function productData(Request $request, ?Product $product = null): array
    {
        return [
            'name' => trim((string) $request->request->get('name', $product?->getName() ?? '')),
            'sellingPrice' => trim((string) $request->request->get('sellingPrice', $product?->getSellingPrice()->toDecimal() ?? '0.00')),
            'sku' => trim((string) $request->request->get('sku', $product?->getSku()?->value() ?? '')),
            'unit' => trim((string) $request->request->get('unit', $product?->getUnit() ?? '')),
            'costPrice' => trim((string) $request->request->get('costPrice', $product?->getCostPrice()?->toDecimal() ?? '')),
            'lowStockThreshold' => max(0, $request->request->getInt('lowStockThreshold', $product?->getLowStockThreshold() ?? 0)),
            'note' => trim((string) $request->request->get('note', $product?->getNote() ?? '')),
        ];
    }

    private function checkCsrf(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    { if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')))) throw $this->createAccessDeniedException('Invalid CSRF token.'); }

    private function safeMessage(\Throwable $e): string
    { return $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'Unable to complete the product operation.'; }
}
