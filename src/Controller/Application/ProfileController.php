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

final class ProfileController extends AbstractController
{
    private const FONT_SIZES = ['small', 'medium', 'large'];

    #[Route('/app/profile', name: 'app_profile', methods: ['GET', 'POST'])]
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
            $token = new CsrfToken('profile_settings', (string) $request->request->get('_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $username = trim((string) $request->request->get('username', $user->getUsername()));
            $fontSize = strtolower(trim((string) $request->request->get('font_size', $user->getFontSize())));

            try {
                $user->changeUsername($username);
                $user->changeFontSize($fontSize);
                $entityManager->flush();

                $this->addFlash('success', 'Thông tin cá nhân đã được lưu.');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Không thể lưu thay đổi. Vui lòng kiểm tra thông tin và thử lại.');
            }

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('application/profile.html.twig', [
            'user' => $user,
            'font_sizes' => self::FONT_SIZES,
        ]);
    }
}
