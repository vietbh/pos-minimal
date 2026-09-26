<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\Security\Permission;
use App\Application\User\ChangePasswordService;
use App\Application\User\ChangeUserRoleService;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
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
        ChangePasswordService $changePasswordService,
        ChangeUserRoleService $changeUserRoleService,
        UserRepositoryInterface $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($request->isMethod('POST')) {
            if ($request->request->has('role_target_id')) {
                $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);

                $roleToken = new CsrfToken('user_role_management', (string) $request->request->get('_token'));
                if (!$csrfTokenManager->isTokenValid($roleToken)) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }

                $targetUserId = (int) $request->request->get('role_target_id');
                $role = UserRole::tryFrom((string) $request->request->get('role'));

                if ($targetUserId <= 0 || $role === null) {
                    $this->addFlash('error', 'Vai trò không hợp lệ.');
                    return $this->redirectToRoute('app_profile');
                }

                try {
                    $changeUserRoleService->change(
                        $user,
                        $targetUserId,
                        $role,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                    );
                    $this->addFlash('success', 'Vai trò người dùng đã được cập nhật.');
                } catch (\DomainException|\RuntimeException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                } catch (\Throwable $exception) {
                    $this->addFlash('error', 'Không thể cập nhật vai trò. Vui lòng thử lại.');
                }

                return $this->redirectToRoute('app_profile');
            }

            $token = new CsrfToken('profile_settings', (string) $request->request->get('_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ($request->request->has('current_password')) {
                try {
                    $changePasswordService->change(
                        $user,
                        (string) $request->request->get('current_password'),
                        (string) $request->request->get('new_password'),
                        (string) $request->request->get('confirm_password'),
                    );
                    $this->addFlash('success', 'Mật khẩu đã được thay đổi.');
                } catch (\DomainException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                } catch (\Throwable $exception) {
                    $this->addFlash('error', 'Không thể thay đổi mật khẩu. Vui lòng thử lại.');
                }
                return $this->redirectToRoute('app_profile');
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

        $managedUsers = [];
        if ($this->isGranted(Permission::USER_MANAGE->value)) {
            $managedUsers = $userRepository->findAllOrderedByUsername();
        }

        return $this->render('application/profile.html.twig', [
            'user' => $user,
            'font_sizes' => self::FONT_SIZES,
            'managed_users' => $managedUsers,
            'can_manage_users' => $this->isGranted(Permission::USER_MANAGE->value),
            'can_manage_root' => $user->hasRole(UserRole::ROOT),
        ]);
    }
}
