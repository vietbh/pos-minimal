<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Application\Security\Permission;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use App\Domain\Payment\Repository\PaymentWebhookSettingsRepositoryInterface;
use App\Infrastructure\Payment\VietQrBankClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PaymentBankAccountController extends AbstractController
{
    #[Route('/admin/settings/payment', name:'admin_payment_accounts', methods:['GET','POST'])]
    public function index(Request $request, PaymentBankAccountRepositoryInterface $accounts, PaymentWebhookSettingsRepositoryInterface $webhookSettingsRepository, CsrfTokenManagerInterface $csrf, VietQrBankClient $vietQrBankClient): Response
    {
        $this->denyAccessUnlessGranted(Permission::PAYMENT_BANK_ACCOUNT_MANAGE->value);
        if ($request->isMethod('POST')) {
            if (!$csrf->isTokenValid(new CsrfToken('admin_payment_account', (string)$request->request->get('_token','')))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            try {
                $selectedBin = trim((string) $request->request->get('bankBin', ''));
                $selectedBank = null;
                foreach ($vietQrBankClient->getBanks() as $bank) {
                    if ((string) $bank['bin'] === $selectedBin) {
                        $selectedBank = $bank;
                        break;
                    }
                }

                if ($selectedBank === null) {
                    throw new \InvalidArgumentException('Please select a valid bank.');
                }

                $accountNumber = trim((string) $request->request->get('accountNumber', ''));
                $accountName = trim((string) $request->request->get('accountName', ''));
                if ($accountNumber === '' || $accountName === '') {
                    throw new \InvalidArgumentException('Account number and account name are required.');
                }

                $accountId = $request->request->getInt('id', 0);
                $account = $accountId > 0 ? $accounts->findById($accountId) : null;
                $isEdit = $account instanceof PaymentBankAccount;

                $qrTemplate = (string) $request->request->get('qrTemplate', 'compact2');
                $transferContentTemplate = (string) $request->request->get('transferContentTemplate', '{PAYMENT REFERENCE} Chuyển khoản cho BUIHOANGVIET');
                $cassoSubAccountId = trim((string) $request->request->get('cassoSubAccountId', '')) ?: null;
                $isActive = $request->request->getBoolean('isActive');

                if ($isEdit) {
                    $account->update(
                        (string) $selectedBank['bin'],
                        (string) $selectedBank['name'],
                        $accountNumber,
                        $accountName,
                        $qrTemplate,
                        $transferContentTemplate,
                        $cassoSubAccountId,
                        $isActive,
                    );
                    $accounts->save($account);
                    $this->addFlash('success','Receiving bank account updated.');
                } else {
                    $account = new PaymentBankAccount(
                        (string) $selectedBank['bin'],
                        (string) $selectedBank['name'],
                        $accountNumber,
                        $accountName,
                        $qrTemplate,
                        $transferContentTemplate,
                        $cassoSubAccountId,
                        $isActive,
                    );
                    $accounts->save($account);
                    $this->addFlash('success','Receiving bank account created.');
                }
                return $this->redirectToRoute('admin_payment_accounts');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Unable to create bank account.');
            }
        }
        $banks = [];
        $bankListError = null;
        try {
            $banks = $vietQrBankClient->getBanks();
        } catch (\Throwable) {
            $bankListError = 'Unable to load the VietQR bank list. Please try again.';
        }

        $webhookSettings = $webhookSettingsRepository->getOrCreate();

        $editId = $request->query->getInt('edit', 0);
        $editAccount = $editId > 0 ? $accounts->findById($editId) : null;

        return $this->render('admin/payment/accounts.html.twig', [
            'accounts'=>$accounts->findAll(),
            'edit_account'=>$editAccount instanceof PaymentBankAccount ? $editAccount : null,
            'banks'=>$banks,
            'bank_list_error'=>$bankListError,
            'csrf_token'=>$csrf->getToken('admin_payment_account')->getValue(),
            'webhook_settings'=>$webhookSettings,
        ]);
    }


    #[Route('/admin/settings/payment/regenerate-webhook-token', name:'admin_payment_regenerate_webhook_token', methods:['POST'])]
    public function regenerateWebhookToken(Request $request, PaymentWebhookSettingsRepositoryInterface $webhookSettingsRepository, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PAYMENT_BANK_ACCOUNT_MANAGE->value);
        if (!$csrf->isTokenValid(new CsrfToken('admin_payment_account_webhook', (string)$request->request->get('_token','')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $settings = $webhookSettingsRepository->getOrCreate();
        $settings->regenerateWebhookToken();
        $webhookSettingsRepository->save($settings);
        $this->addFlash('success','Global webhook token regenerated. Update the bank notification client with the new token.');

        return $this->redirectToRoute('admin_payment_accounts');
    }

    #[Route('/admin/settings/payment/{id<\d+>}/toggle', name:'admin_payment_account_toggle', methods:['POST'])]
    public function toggle(int $id, Request $request, PaymentBankAccountRepositoryInterface $accounts, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::PAYMENT_BANK_ACCOUNT_MANAGE->value);
        if (!$csrf->isTokenValid(new CsrfToken('admin_payment_account_toggle', (string)$request->request->get('_token','')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
        $account=$accounts->findById($id);
        if (!$account instanceof PaymentBankAccount) throw $this->createNotFoundException('Bank account not found.');
        $account->setActive(!$account->isActive());
        $accounts->save($account);
        return $this->redirectToRoute('admin_payment_accounts');
    }
}
