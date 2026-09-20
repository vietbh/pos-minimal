<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Security\RuntimeActorContextProvider;
use App\Application\Security\ActorContext;
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
use App\Application\Product\Query\ProductCatalogHandler;
use App\Application\Product\Query\ProductCatalogInput;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Application\Security\Permission;
use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
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
    public function index(Request $request, ProductCatalogHandler $catalog, ProductCategoryRepositoryInterface $categories): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_VIEW->value);
        $q = trim((string) $request->query->get('q', ''));
        $categoryRaw = $request->query->get('category');
        $categoryId = is_numeric($categoryRaw) && (int) $categoryRaw > 0 ? (int) $categoryRaw : null;
        $sort = (string) $request->query->get('sort', 'name_asc');
        $pageRaw = $request->query->get('page', 1);
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;
        $result = $catalog(new ProductCatalogInput($q, $categoryId, $sort, $page, 20));
        if ($result->page > $result->totalPages && $result->total > 0) {
            $result = $catalog(new ProductCatalogInput($q, $categoryId, $sort, $result->totalPages, 20));
        }
        return $this->render('admin/product/index.html.twig', [
            'products' => $result->items, 'pagination' => $result, 'q' => $q, 'categoryId' => $categoryId,
            'sort' => in_array($sort, ['name_asc','name_desc'], true) ? $sort : 'name_asc',
            'categories' => $categories->findActiveOrdered(),
        ]);
    }

    #[Route('/admin/products/new', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateProductHandler $handler, ProductCategoryRepositoryInterface $categoryRepository, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_CREATE->value);
        $data = $this->productData($request);
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, $csrf, 'admin_product_form');
            try {
                $this->validateProductFormData($request, $data, false);
                $id = $handler(new CreateProductInput(
                    name: $data['name'], sellingPrice: Money::fromDecimal($data['sellingPrice']),
                    sku: $data['sku'] !== '' ? new Sku($data['sku']) : null,
                    unit: $data['unit'] !== '' ? $data['unit'] : null,
                    costPrice: $data['costPrice'] !== '' ? Money::fromDecimal($data['costPrice']) : null,
                    lowStockThreshold: $data['lowStockThreshold'], note: $data['note'] !== '' ? $data['note'] : null, categoryId: $data['categoryId'], categoryName: $data['categoryName'] !== '' ? $data['categoryName'] : null,
                ));
                $this->addFlash('success', 'Product created.');
                return $this->redirectToRoute('admin_products_show', ['id' => $id]);
            } catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        }
        $response = $this->render('admin/product/form.html.twig', [
            'product' => null,
            'data' => $data,
            'categories' => $categoryRepository->findActiveOrdered(),
        ]);
        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
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
    public function edit(int $id, Request $request, ProductRepositoryInterface $products, ProductCategoryRepositoryInterface $categoryRepository, UpdateProductHandler $handler, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PRODUCT_EDIT->value);
        $product = $products->findById($id);
        if (!$product instanceof Product) throw $this->createNotFoundException('Product not found.');
        $data = $this->productData($request, $product);
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, $csrf, 'admin_product_form');
            try {
                $this->validateProductFormData($request, $data, true);
                $handler(new UpdateProductInput($id, $data['name'], $data['sku'] !== '' ? new Sku($data['sku']) : null, $data['unit'] !== '' ? $data['unit'] : null, $data['costPrice'] !== '' ? Money::fromDecimal($data['costPrice']) : null, $data['lowStockThreshold'], $data['note'] !== '' ? $data['note'] : null, $data['categoryId']));
                $this->addFlash('success', 'Product updated.');
                return $this->redirectToRoute('admin_products_show', ['id' => $id]);
            } catch (\Throwable $e) { $this->addFlash('error', $this->safeMessage($e)); }
        }
        $response = $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'data' => $data,
            'categories' => $categoryRepository->findActiveOrdered(),
        ]);
        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
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
    public function stock(
        int $id,
        Request $request,
        AdjustStockHandlerEntryPoint $handler,
        CsrfTokenManagerInterface $csrf,
        RuntimeActorContextProvider $actorContextProvider,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::STOCK_ADJUST->value);
        $this->checkCsrf($request, $csrf, 'admin_stock_adjust');

        $key = trim((string) $request->headers->get(
            'Idempotency-Key',
            $request->request->get('idempotencyKey', '')
        ));

        if ($key === '') {
            $this->addFlash('error', 'Idempotency key is required.');

            return $this->redirectToRoute('admin_products_show', [
                'id' => $id,
            ]);
        }

        try {
            $user = $this->getUser();

            if (!$user instanceof User || !$user->isActive()) {
                throw $this->createAccessDeniedException('Authentication is required.');
            }

            $requestId = $request->headers->get('X-Request-ID')
                ?: bin2hex(random_bytes(16));

            $actorContextProvider->set(
                new ActorContext(
                    $user->getId() ?? 0,
                    null,
                    $requestId,
                )
            );

            try {
                $handler->handle(
                    new AdjustStockInput(
                        $id,
                        $request->request->getInt('quantityChange'),
                        trim((string) $request->request->get('reason', '')) ?: null,
                        $key,
                    )
                );

                $this->addFlash('success', 'Stock updated.');
            } finally {
                $actorContextProvider->clear();
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->safeMessage($e));
        }

        return $this->redirectToRoute('admin_products_show', [
            'id' => $id,
        ]);
    }

    /** @return array{name:string,sellingPrice:string,sku:string,unit:string,costPrice:string,lowStockThreshold:int,note:string,categoryId:?int,categoryName:string} */
    private function productData(Request $request, ?Product $product = null): array
    {
        $thresholdRaw = $request->request->get('lowStockThreshold');
        $threshold = $product?->getLowStockThreshold() ?? 0;
        if ($thresholdRaw !== null && is_scalar($thresholdRaw) && preg_match('/^\d+$/', trim((string) $thresholdRaw)) === 1) {
            $threshold = (int) $thresholdRaw;
        }

        $categoryId = $product?->getCategory()?->getId();
        if ($request->request->has('categoryId')) {
            $categoryRaw = trim((string) $request->request->get('categoryId', ''));
            $categoryId = $categoryRaw !== '' && ctype_digit($categoryRaw) && (int) $categoryRaw > 0
                ? (int) $categoryRaw
                : null;
        }

        return [
            'name' => trim((string) $request->request->get('name', $product?->getName() ?? '')),
            'sellingPrice' => trim((string) $request->request->get('sellingPrice', $product?->getSellingPrice()->toDecimal() ?? '')),
            'sku' => trim((string) $request->request->get('sku', $product?->getSku()?->value() ?? '')),
            'unit' => trim((string) $request->request->get('unit', $product?->getUnit() ?? '')),
            'costPrice' => trim((string) $request->request->get('costPrice', $product?->getCostPrice()?->toDecimal() ?? '')),
            'lowStockThreshold' => $threshold,
            'note' => trim((string) $request->request->get('note', $product?->getNote() ?? '')),
            'categoryId' => $categoryId,
            'categoryName' => trim((string) $request->request->get('categoryName', '')),
        ];
    }

    /** @param array{name:string,sellingPrice:string,sku:string,unit:string,costPrice:string,lowStockThreshold:int,note:string,categoryId:?int,categoryName:string} $data */
    private function validateProductFormData(Request $request, array $data, bool $editing): void
    {
        if ($data['name'] === '') {
            throw new \InvalidArgumentException('Tên sản phẩm là bắt buộc.');
        }
        if (mb_strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Tên sản phẩm không được vượt quá 255 ký tự.');
        }

        if ($data['sellingPrice'] === '') {
            throw new \InvalidArgumentException('Giá bán là bắt buộc.');
        }
        $this->validateMoneyField($data['sellingPrice'], 'Giá bán');
        if ($data['costPrice'] !== '') {
            $this->validateMoneyField($data['costPrice'], 'Giá vốn');
        }

        $sku = $data['sku'];
        if ($sku !== '' && mb_strlen($sku) > 100) {
            throw new \InvalidArgumentException('SKU không được vượt quá 100 ký tự.');
        }
        if (mb_strlen($data['unit']) > 50) {
            throw new \InvalidArgumentException('Đơn vị không được vượt quá 50 ký tự.');
        }

        $thresholdRaw = $request->request->get('lowStockThreshold');
        if ($thresholdRaw !== null && trim((string) $thresholdRaw) !== '' && preg_match('/^\d+$/', trim((string) $thresholdRaw)) !== 1) {
            throw new \InvalidArgumentException('Ngưỡng tồn kho thấp phải là số nguyên không âm.');
        }
        if ($data['lowStockThreshold'] < 0) {
            throw new \InvalidArgumentException('Ngưỡng tồn kho thấp không được âm.');
        }

        $categoryRaw = $request->request->get('categoryId');
        if ($categoryRaw !== null) {
            $categoryRaw = trim((string) $categoryRaw);
            if ($categoryRaw !== '' && (preg_match('/^\d+$/', $categoryRaw) !== 1 || (int) $categoryRaw <= 0)) {
                throw new \InvalidArgumentException('Danh mục không hợp lệ.');
            }
        }

        if (!$editing && $data['categoryId'] !== null && $data['categoryName'] !== '') {
            throw new \InvalidArgumentException('Chỉ chọn một danh mục hoặc tạo danh mục mới.');
        }
        if (mb_strlen($data['categoryName']) > 150) {
            throw new \InvalidArgumentException('Tên danh mục không được vượt quá 150 ký tự.');
        }
    }

    private function validateMoneyField(string $value, string $label): void
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new \InvalidArgumentException($label . ' phải là số không âm, tối đa 2 chữ số thập phân.');
        }
        if (str_contains($value, '.') && preg_match('/[^0]/', substr($value, strpos($value, '.') + 1)) === 1) {
            throw new \InvalidArgumentException($label . ' bằng VND không được có phần thập phân khác 0.');
        }
    }

    private function checkCsrf(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    { if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')))) throw $this->createAccessDeniedException('Invalid CSRF token.'); }

    private function safeMessage(\Throwable $e): string
    { return $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'Unable to complete the product operation.'; }
}
