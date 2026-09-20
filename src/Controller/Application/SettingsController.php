<?php

declare(strict_types=1);

namespace App\Controller\Application;

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

            try {
                $user->changeFontSize($fontSize);
                $user->changeAppearance($appearance);
                $user->changeUiDensity($density);
                $user->setHighContrast($highContrast);
                $user->setReduceMotion($reduceMotion);

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
        ]);
    }
}
