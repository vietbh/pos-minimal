<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Application\Product\Command\DeleteProductImage\DeleteProductImageHandler;
use App\Application\Product\Command\DeleteProductImage\DeleteProductImageInput;
use App\Application\Product\Command\UploadProductImage\UploadProductImageHandler;
use App\Application\Product\Command\UploadProductImage\UploadProductImageInput;
use App\Application\Product\Image\ImageUpload;
use App\Domain\Product\Repository\ProductImageRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\User\User;
use App\Application\Common\Storage\FileStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProductImageController extends AbstractController
{
    #[Route('/products/{productId}/images/upload', name: 'product_image_upload_form', methods: ['GET'], requirements: ['productId' => '\\d+'])]
    public function form(int $productId, ProductRepositoryInterface $products): Response
    {
        $product = $products->findById($productId);
        if ($product === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('product/image_upload.html.twig', [
            'productId' => $productId,
            'product' => $product,
        ]);
    }

    #[Route('/products/{productId}/images', name: 'product_image_upload', methods: ['POST'], requirements: ['productId' => '\\d+'])]
    public function upload(
        int $productId,
        Request $request,
        UploadProductImageHandler $handler,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null || !$user->isActive()) {
            return $this->json(['errorCode' => 'AUTHENTICATION_REQUIRED'], Response::HTTP_UNAUTHORIZED);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', $request->request->get('_token', ''));
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('product_image_upload', $csrfToken))) {
            return $this->json(['errorCode' => 'CSRF_INVALID'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('image');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['errorCode' => 'IMAGE_REQUIRED'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $id = $handler(new UploadProductImageInput(
                productId: $productId,
                upload: new ImageUpload(
                    path: $file->getPathname(),
                    originalFilename: $file->getClientOriginalName(),
                    clientMimeType: $file->getClientMimeType(),
                    size: (int) $file->getSize(),
                ),
                actorId: $user->getId(),
            ));
        } catch (\Throwable $exception) {
            if ($exception instanceof \App\Application\Product\Image\ImageUploadException) {
                return $this->json([
                    'errorCode' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->json([
                'errorCode' => 'PRODUCT_IMAGE_UPLOAD_FAILED',
                'message' => 'Unable to upload the image.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'id' => $id,
            'status' => 'UPLOADING',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/product-images/{id}/{variant}', name: 'product_image_serve', methods: ['GET'], requirements: ['id' => '\\d+', 'variant' => 'original|thumbnail|medium'])]
    public function serve(
        int $id,
        string $variant,
        ProductImageRepositoryInterface $repository,
        FileStorageInterface $storage,
    ): Response {
        $image = $repository->findById($id);
        if ($image === null) {
            throw $this->createNotFoundException();
        }

        $key = $image->getStorageKey();
        if ($variant !== 'original') {
            $key = dirname($key) . '/' . $variant . '.webp';
        }

        $path = $storage->path($key);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $variant === 'original' ? $image->getMimeType() : 'image/webp');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }

    #[Route('/product-images/{id}', name: 'product_image_delete', methods: ['DELETE'], requirements: ['id' => '\\d+'])]
    public function delete(
        int $id,
        DeleteProductImageHandler $handler,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null || !$user->isActive()) {
            return $this->json(['errorCode' => 'AUTHENTICATION_REQUIRED'], Response::HTTP_UNAUTHORIZED);
        }

        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', $request->request->get('_token', ''));
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('product_image_delete', $csrfToken))) {
            return $this->json(['errorCode' => 'CSRF_INVALID'], Response::HTTP_FORBIDDEN);
        }

        $handler(new DeleteProductImageInput(
            productImageId: $id,
            actorId: $user->getId(),
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
