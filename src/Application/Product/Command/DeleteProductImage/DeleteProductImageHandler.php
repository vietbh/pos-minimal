<?php

declare(strict_types=1);

namespace App\Application\Product\Command\DeleteProductImage;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Product\Repository\ProductImageRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Application\Common\Storage\FileStorageInterface;

final class DeleteProductImageHandler
{
    public function __construct(
        private readonly ProductImageRepositoryInterface $imageRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly FileStorageInterface $storage,
    ) {
    }

    public function __invoke(DeleteProductImageInput $input): void
    {
        $storageKey = $this->transactionManager->run(function () use ($input): ?string {
            $image = $this->imageRepository->findByIdForUpdate($input->productImageId);
            if ($image === null) {
                return null;
            }

            $productId = $image->getProduct()->getId();
            $wasPrimary = $image->isPrimary();
            $storageKey = $image->getStorageKey();

            $this->imageRepository->remove($image);

            if ($wasPrimary && $productId !== null) {
                $replacement = $this->imageRepository->findFirstReadyByProductId(
                    $productId,
                    $input->productImageId,
                );
                if ($replacement !== null) {
                    $replacement->markAsPrimary();
                }
            }

            $user = $this->userRepository->findById($input->actorId);
            if ($user !== null) {
                $this->auditLogRepository->save(new AuditLog(
                    action: 'PRODUCT_IMAGE_DELETED',
                    user: $user,
                    entityType: 'ProductImage',
                    entityId: (string) $input->productImageId,
                    oldValues: ['storageKey' => $storageKey],
                ));
            }

            return $storageKey;
        });

        if ($storageKey === null) {
            return;
        }

        try {
            $this->storage->delete($storageKey);
        } catch (\Throwable) {
            // Storage cleanup is intentionally best-effort after the DB commit.
        }

        $directory = dirname($storageKey);
        foreach (['thumbnail.webp', 'medium.webp'] as $variant) {
            try {
                $this->storage->delete($directory . '/' . $variant);
            } catch (\Throwable) {
            }
        }
    }
}
