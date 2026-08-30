<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UploadProductImage;

use App\Application\Product\Image\ImageUploadValidator;
use App\Application\Product\Message\ProcessProductImage;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Product\ProductImage;
use App\Domain\Product\Repository\ProductImageRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Application\Common\Storage\FileStorageInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class UploadProductImageHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductImageRepositoryInterface $imageRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository,
        private readonly ImageUploadValidator $validator,
        private readonly FileStorageInterface $storage,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(UploadProductImageInput $input): int
    {
        $product = $this->productRepository->findById($input->productId);
        if ($product === null) {
            throw new \DomainException('Product not found.');
        }

        $validated = $this->validator->validate($input->upload);
        $extension = $validated->extension;
        $token = bin2hex(random_bytes(16));
        $storageKey = sprintf(
            'products/%d/%s/original.%s',
            $input->productId,
            $token,
            $extension,
        );

        $this->storage->putFile($input->upload->path, $storageKey);

        try {
            $imageId = $this->transactionManager->run(function () use ($input, $product, $validated, $storageKey): int {
                $image = new ProductImage(
                    storageKey: $storageKey,
                    mimeType: $validated->mimeType,
                    size: $validated->size,
                    originalFilename: $input->upload->originalFilename,
                    width: $validated->width,
                    height: $validated->height,
                );

                $image->assignProduct($product);

                if ($this->imageRepository->countByProductId($product->getId() ?? 0) === 0) {
                    $image->markAsPrimary();
                }

                $this->imageRepository->save($image);

                $user = $this->userRepository->findById($input->actorId);
                if ($user !== null) {
                    $this->auditLogRepository->save(new AuditLog(
                        action: 'PRODUCT_IMAGE_UPLOADED',
                        user: $user,
                        entityType: 'ProductImage',
                        entityId: null,
                        newValues: [
                            'productId' => $input->productId,
                            'storageKey' => $storageKey,
                            'mimeType' => $validated->mimeType,
                            'size' => $validated->size,
                        ],
                    ));
                }

                return $image->getId() ?? throw new \LogicException('Product image ID was not generated.');
            });
        } catch (\Throwable $exception) {
            try {
                $this->storage->delete($storageKey);
            } catch (\Throwable) {
                // The orphan is intentionally left for storage cleanup/reconciliation.
            }

            throw $exception;
        }

        try {
            $this->messageBus->dispatch(new ProcessProductImage($imageId));
        } catch (\Throwable) {
            // The image remains UPLOADING. A future reconciliation/maintenance job can re-dispatch it.
        }

        return $imageId;
    }
}
