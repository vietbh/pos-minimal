<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Common\Storage\FileStorageInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Import\Message\ProcessImport;
use App\Application\Import\Service\ImportService;
use App\Application\Security\Permission;
use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\ImportType;
use App\Domain\Import\Repository\ImportBatchRepositoryInterface;
use App\Domain\Import\Repository\ImportRowErrorRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/import')]
final class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportService $service,
        private readonly ImportBatchRepositoryInterface $batches,
        private readonly ImportRowErrorRepositoryInterface $errors,
        private readonly FileStorageInterface $storage,
        private readonly TransactionManagerInterface $transactions,
        private readonly MessageBusInterface $bus,
        private readonly int $maxRows,
        private readonly int $maxBytes,
    ) {}

    #[Route('/product/template', name: 'admin_import_product_template', methods: ['GET'])]
    public function productTemplate(): Response { return $this->template(ImportType::PRODUCT); }

    #[Route('/stock/template', name: 'admin_import_stock_template', methods: ['GET'])]
    public function stockTemplate(): Response { return $this->template(ImportType::STOCK); }

    #[Route('/customer/template', name: 'admin_import_customer_template', methods: ['GET'])]
    public function customerTemplate(): Response { return $this->template(ImportType::CUSTOMER); }

    #[Route('/product', name: 'admin_import_product', methods: ['GET', 'POST'])]
    public function product(Request $request, CsrfTokenManagerInterface $csrf): Response { return $this->uploadPage($request, $csrf, ImportType::PRODUCT); }

    #[Route('/stock', name: 'admin_import_stock', methods: ['GET', 'POST'])]
    public function stock(Request $request, CsrfTokenManagerInterface $csrf): Response { return $this->uploadPage($request, $csrf, ImportType::STOCK); }

    #[Route('/customer', name: 'admin_import_customer', methods: ['GET', 'POST'])]
    public function customer(Request $request, CsrfTokenManagerInterface $csrf): Response { return $this->uploadPage($request, $csrf, ImportType::CUSTOMER); }

    #[Route('/{id<\d+>}/confirm', name: 'admin_import_confirm', methods: ['POST'])]
    public function confirm(int $id, Request $request, CsrfTokenManagerInterface $csrf): Response
    {
        $batch = $this->batches->findById($id);
        if (!$batch instanceof ImportBatch) throw $this->createNotFoundException('Import not found.');
        $this->denyImportPermission($batch->getType());
        $this->checkCsrf($request, $csrf, 'admin_import_confirm_'.$id);
        $this->assertOwnerOrPermission($batch);
        if ($batch->getStatus() !== ImportStatus::READY) {
            $this->addFlash('error', 'This import is not ready for confirmation.');
            return $this->redirectToRoute('admin_import_detail', ['id' => $id]);
        }
        $this->transactions->run(function ($tx) use ($batch): void { $batch->confirmQueue(); $this->batches->save($batch); $tx->flush(); });
        $this->bus->dispatch(new ProcessImport($id));
        $this->addFlash('success', 'Import queued for processing.');
        return $this->redirectToRoute('admin_import_detail', ['id' => $id]);
    }

    #[Route('/{id<\d+>}/preview', name: 'admin_import_preview_save', methods: ['POST'])]
    public function savePreview(int $id, Request $request, CsrfTokenManagerInterface $csrf): Response
    {
        $batch = $this->batches->findById($id);
        if (!$batch instanceof ImportBatch) throw $this->createNotFoundException('Import not found.');
        $this->denyImportPermission($batch->getType());
        $this->assertOwnerOrPermission($batch);
        $this->checkCsrf($request, $csrf, 'admin_import_preview_'.$id);
        try {
            $rows = $request->request->all('rows');
            if (!is_array($rows)) throw new \InvalidArgumentException('IMPORT_PREVIEW_INVALID_ROW: Preview data is invalid.');
            $this->service->savePreview($batch, $rows);
            $this->addFlash('success', 'Preview đã được lưu và kiểm tra lại.');
            return $this->redirectToRoute('admin_import_detail', ['id' => $id]);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'Unable to save import preview.');
            return $this->redirectToRoute('admin_import_detail', ['id' => $id]);
        }
    }

    #[Route('/{id<\d+>}', name: 'admin_import_detail', methods: ['GET'])]
    public function detail(int $id): Response
    {
        $batch = $this->batches->findById($id);
        if (!$batch instanceof ImportBatch) throw $this->createNotFoundException('Import not found.');
        $this->denyImportPermission($batch->getType());
        $this->assertOwnerOrPermission($batch);
        return $this->render('admin/import/detail.html.twig', [
            'batch' => $batch,
            'errors' => $this->errors->findByBatch($batch, 2000),
            'rows' => $this->service->previewRows($batch),
            'previewPayloads' => $this->service->previewPayloads($batch),
            'headers' => $this->service->previewHeaders($batch->getType()),
        ]);
    }

    #[Route('/history', name: 'admin_import_history', methods: ['GET'])]
    public function history(Request $request): Response
    {
        if (!$this->isGranted(Permission::PRODUCT_VIEW->value) && !$this->isGranted(Permission::STOCK_ADJUST->value) && !$this->isGranted(Permission::CUSTOMER_VIEW->value)) {
            throw $this->createAccessDeniedException('Import history requires product-view or stock-adjust access.');
        }
        $pageRaw = $request->query->get('page', 1);
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;
        $perPage = 20;
        $user = $this->getUser();
        $ownerId = null;
        if ($user instanceof User && !$this->isGranted(Permission::USER_MANAGE->value) && !$this->isGranted(Permission::AUDIT_VIEW->value)) {
            $ownerId = $user->getId();
        }
        $result = $this->batches->findPage($page, $perPage, $ownerId);
        $totalPages = max(1, (int) ceil($result['total'] / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $this->batches->findPage($page, $perPage, $ownerId);
        }
        return $this->render('admin/import/history.html.twig', [
            'batches' => $result['items'], 'page' => $page, 'perPage' => $perPage,
            'total' => $result['total'], 'totalPages' => $totalPages,
        ]);
    }

    #[Route('/history/delete', name: 'admin_import_history_delete', methods: ['POST'])]
    public function deleteHistory(Request $request, CsrfTokenManagerInterface $csrf): Response
    {
        if (!$this->isGranted(Permission::USER_MANAGE->value) && !$this->isGranted(Permission::AUDIT_VIEW->value)) {
            throw $this->createAccessDeniedException('Deleting import history requires user-manage or audit-view access.');
        }
        $this->checkCsrf($request, $csrf, 'admin_import_history_delete');
        $rawIds = $request->request->all('ids');
        $ids = is_array($rawIds) ? array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0))) : [];
        if ($ids === []) {
            $this->addFlash('error', 'Please select at least one import history item.');
            return $this->redirectToRoute('admin_import_history');
        }

        $batches = $this->batches->findByIds($ids);
        $deletable = [];
        $blocked = [];
        foreach ($batches as $batch) {
            if (in_array($batch->getStatus(), [ImportStatus::VALIDATION_FAILED, ImportStatus::COMPLETED, ImportStatus::PARTIAL, ImportStatus::FAILED], true)) {
                $deletable[] = $batch;
            } else {
                $blocked[] = $batch;
            }
        }
        if ($deletable !== []) {
            $this->transactions->run(function ($tx) use ($deletable): void {
                foreach ($deletable as $batch) $this->batches->remove($batch);
                $tx->flush();
            });
            $removedFiles = 0;
            foreach ($deletable as $batch) {
                try { $this->storage->delete($batch->getStorageKey()); ++$removedFiles; } catch (\Throwable) {}
            }
            $this->addFlash('success', sprintf('Deleted %d import history item(s).', count($deletable)));
            if ($removedFiles < count($deletable)) {
                $this->addFlash('warning', 'Some stored Excel files could not be removed automatically.');
            }
        }
        if ($blocked !== []) {
            $this->addFlash('warning', sprintf('%d import(s) were not deleted because they are still active or queued.', count($blocked)));
        }
        return $this->redirectToRoute('admin_import_history');
    }

    #[Route('/{id<\d+>}/errors', name: 'admin_import_errors', methods: ['GET'])]
    public function errors(int $id): Response
    {
        $batch = $this->batches->findById($id);
        if (!$batch instanceof ImportBatch) throw $this->createNotFoundException('Import not found.');
        $this->denyImportPermission($batch->getType());
        $this->assertOwnerOrPermission($batch);
        $rows = [['Row', 'Field', 'Error Code', 'Message', 'Value']];
        foreach ($this->errors->findByBatch($batch, 10000) as $error) $rows[] = [$error->getRowNumber(), $error->getField(), $error->getErrorCode(), $error->getMessage(), $error->getRawValue()];
        $content = implode("\n", array_map(static fn(array $r): string => implode(',', array_map(static fn($v): string => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'import-'.$id.'-errors.csv'));
        return $response;
    }

    private function template(ImportType $type): Response
    {
        $this->denyImportPermission($type);
        $content = $this->service->template($type);
        $name = match ($type) { ImportType::PRODUCT => 'product-import-template-v2.xlsx', ImportType::STOCK => 'stock-import-template-v3.xlsx', ImportType::CUSTOMER => 'customer-import-template-v1.xlsx' };
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name));
        return $response;
    }

    private function uploadPage(Request $request, CsrfTokenManagerInterface $csrf, ImportType $type): Response
    {
        $this->denyImportPermission($type);
        $batch = null;
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, $csrf, 'admin_import_'.$type->value);
            $user = $this->getUser();
            if (!$user instanceof User || !$user->isActive() || $user->getId() === null) throw $this->createAccessDeniedException('Authentication is required.');
            $file = $request->files->get('file');
            try {
                if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) throw new \InvalidArgumentException('IMPORT_INVALID_FILE: Please choose an XLSX file.');
                $batch = $this->service->upload($file, $type, $user, $request->headers->get('X-Request-ID'), $this->maxRows, $this->maxBytes);
                $this->addFlash($batch->getStatus() === ImportStatus::READY ? 'success' : 'error', $batch->getStatus() === ImportStatus::READY ? 'File validated successfully.' : 'File validation failed.');
                return $this->redirectToRoute('admin_import_detail', ['id' => $batch->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'Unable to validate import file.');
            }
        }
        return $this->render('admin/import/upload.html.twig', ['type' => $type, 'batch' => $batch]);
    }

    private function denyImportPermission(ImportType $type): void
    {
        $permission = match ($type) { ImportType::PRODUCT => Permission::PRODUCT_CREATE->value, ImportType::STOCK => Permission::STOCK_ADJUST->value, ImportType::CUSTOMER => Permission::CUSTOMER_CREATE->value };
        $this->denyAccessUnlessGranted($permission);
    }

    private function assertOwnerOrPermission(ImportBatch $batch): void
    {
        $user = $this->getUser();
        $id = $user instanceof User ? $user->getId() : null;
        if ($id !== null && $batch->getRequestedBy()->getId() === $id) return;
        if ($this->isGranted(Permission::USER_MANAGE->value) || $this->isGranted(Permission::AUDIT_VIEW->value)) return;
        throw $this->createAccessDeniedException('You are not allowed to access this import.');
    }

    private function checkCsrf(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
    }
}
