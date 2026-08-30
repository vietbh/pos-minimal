<?php

declare(strict_types=1);

namespace App\Application\Product\Message;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Product\Image\ImageProcessorInterface;
use App\Domain\Product\Enum\ImageStatus;
use App\Domain\Product\Repository\ProductImageRepositoryInterface;
use App\Application\Common\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessProductImageHandler
{
    public function __construct(
        private readonly ProductImageRepositoryInterface $imageRepository,
        private readonly ImageProcessorInterface $processor,
        private readonly FileStorageInterface $storage,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(ProcessProductImage $message): void
    {
        $claimed = $this->transactionManager->run(function () use ($message): bool {
            $image = $this->imageRepository->findByIdForUpdate($message->productImageId);
            if ($image === null) {
                return false;
            }

            if ($image->getStatus() === ImageStatus::READY) {
                return false;
            }

            if ($image->getStatus() === ImageStatus::PROCESSING) {
                return false;
            }

            if ($image->getStatus() === ImageStatus::FAILED) {
                $image->retryProcessing();
            } else {
                $image->markProcessing();
            }

            return true;
        });

        if (!$claimed) {
            return;
        }

        $image = $this->imageRepository->findById($message->productImageId);
        if ($image === null) {
            return;
        }

        try {
            $sourcePath = $this->storage->path($image->getStorageKey());
            $outputDirectory = dirname($sourcePath);
            $this->processor->process($sourcePath, $outputDirectory);

            $this->transactionManager->run(function () use ($message): void {
                $image = $this->imageRepository->findByIdForUpdate($message->productImageId);
                if ($image === null || $image->getStatus() !== ImageStatus::PROCESSING) {
                    return;
                }

                $image->markReady();
            });
        } catch (\Throwable $exception) {
            $this->transactionManager->run(function () use ($message): void {
                $image = $this->imageRepository->findByIdForUpdate($message->productImageId);
                if ($image !== null && $image->getStatus() === ImageStatus::PROCESSING) {
                    $image->markFailed();
                }
            });

            throw $exception;
        }
    }
}
