<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\Dashboard\DashboardNavigationResolver;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SettingsController extends AbstractController
{
    private const FONT_SIZES = ['small', 'medium', 'large'];
    private const APPEARANCES = ['light', 'dark', 'system'];
    private const DENSITIES = ['comfortable', 'standard', 'compact'];

    #[Route('/app/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        DashboardNavigationResolver $navigationResolver,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($request->isMethod('POST')) {
            $token = new CsrfToken(
                'user_preferences',
                (string) $request->request->get('_token'),
            );

            if (!$csrfTokenManager->isTokenValid($token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $fontSize = strtolower(trim((string) $request->request->get('font_size', $user->getFontSize())));
            $appearance = strtolower(trim((string) $request->request->get('appearance', $user->getAppearance())));
            $density = strtolower(trim((string) $request->request->get('density', $user->getUiDensity())));
            $highContrast = $request->request->getBoolean('high_contrast');
            $reduceMotion = $request->request->getBoolean('reduce_motion');
            $paymentSoundEnabled = $request->request->getBoolean('payment_sound_enabled');
            $usageGuideEnabled = $request->request->getBoolean('usage_guide_enabled');
            $dashboardDefaultTab = strtolower(trim((string) $request->request->get('dashboard_default_tab', $user->getDashboardDefaultTab())));

            $allowedDashboardTabs = array_column($navigationResolver->availableTabs($user), 'key');
            if (!in_array($dashboardDefaultTab, ['auto', ...$allowedDashboardTabs], true)) {
                $this->addFlash('error', 'Tab mặc định Trang chủ không hợp lệ hoặc bạn không có quyền sử dụng tab này.');
                return $this->redirectToRoute('app_settings');
            }

            try {
                $user->changeFontSize($fontSize);
                $user->changeAppearance($appearance);
                $user->changeUiDensity($density);
                $user->setHighContrast($highContrast);
                $user->setReduceMotion($reduceMotion);
                $user->setPaymentSoundEnabled($paymentSoundEnabled);
                $user->setUsageGuideEnabled($usageGuideEnabled);
                $user->changeDashboardDefaultTab($dashboardDefaultTab);

                $entityManager->flush();

                $this->addFlash('success', 'Cài đặt đã được lưu.');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable) {
                $this->addFlash(
                    'error',
                    'Không thể lưu cài đặt. Vui lòng kiểm tra thông tin và thử lại.',
                );
            }

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('application/settings.html.twig', [
            'user' => $user,
            'font_sizes' => self::FONT_SIZES,
            'appearances' => self::APPEARANCES,
            'densities' => self::DENSITIES,
            'dashboard_tabs' => $navigationResolver->availableTabs($user),
        ]);
    }
}
