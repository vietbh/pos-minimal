<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Security\Permission;
use App\Application\Security\UserManagementPolicy;
use App\Application\User\UserManagementService;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/users')]
final class UserController extends AbstractController
{
    private const PAGE_SIZE = 20;
    private const CSRF_FORM = 'admin_user_form';
    private const CSRF_STATE = 'admin_user_state';
    private const CSRF_PASSWORD = 'admin_user_password';

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepositoryInterface $repository, UserManagementPolicy $policy): Response
    {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);

        $actor = $this->requireUser();

        $search = trim((string) $request->query->get('q', ''));
        $status = strtolower(trim((string) $request->query->get('status', 'all')));
        $active = match ($status) {
            'active' => true,
            'inactive' => false,
            default => null,
        };
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
            $active = null;
        }

        $roleValue = trim((string) $request->query->get('role', ''));
        $role = $roleValue !== '' ? UserRole::tryFrom($roleValue) : null;
        if ($roleValue !== '' && $role === null) {
            $roleValue = '';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $visibleRoles = $policy->visibleRoles($actor);
        if ($role !== null && !in_array($role, $visibleRoles, true)) {
            $role = null;
            $roleValue = '';
        }
        $result = $repository->searchPage($search !== '' ? $search : null, $active, $role, $page, self::PAGE_SIZE, $visibleRoles);
        $totalPages = max(1, (int) ceil($result['total'] / self::PAGE_SIZE));
        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $repository->searchPage($search !== '' ? $search : null, $active, $role, $page, self::PAGE_SIZE, $visibleRoles);
        }

        return $this->render('admin/user/index.html.twig', [
            'users' => $result['items'],
            'pagination' => [
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $result['total'],
            ],
            'q' => $search,
            'status' => $status,
            'role' => $roleValue,
            'roles' => $policy->visibleRoles($actor),
            'actor' => $actor,
            'policy' => $policy,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserManagementService $service,
        CsrfTokenManagerInterface $csrf,
        UserManagementPolicy $policy,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);
        $actor = $this->requireUser();

        $data = [
            'username' => trim((string) $request->request->get('username', '')),
            'role' => (string) $request->request->get('role', UserRole::USER->value),
        ];
        $error = null;

        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, $csrf, self::CSRF_FORM);
            $role = UserRole::tryFrom((string) $data['role']);
            $password = (string) $request->request->get('password', '');
            $confirmation = (string) $request->request->get('password_confirmation', '');

            if ($role === null) {
                $error = 'Invalid role.';
            } elseif (!$policy->canCreateRole($actor, $role)) {
                $error = 'You cannot create an account at this role level.';
            } elseif ($password !== $confirmation) {
                $error = 'Password confirmation does not match.';
            } else {
                try {
                    $service->create($actor, (string) $data['username'], $password, $role, $request->getClientIp(), $request->headers->get('User-Agent'));
                    $this->addFlash('success', 'User created successfully.');
                    return $this->redirectToRoute('admin_users_index');
                } catch (\InvalidArgumentException|\DomainException $e) {
                    $error = $e->getMessage();
                } catch (\Throwable) {
                    $error = 'Unable to create user.';
                }
            }
        }

        return $this->render('admin/user/form.html.twig', [
            'mode' => 'create',
            'user' => null,
            'data' => $data,
            'roles' => $policy->manageableRoles($actor),
            'error' => $error,
        ], $request->isMethod('POST') ? new Response('', Response::HTTP_UNPROCESSABLE_ENTITY) : new Response());
    }

    #[Route('/{id<\d+>}', name: 'admin_users_show', methods: ['GET'])]
    public function show(int $id, Request $request, UserRepositoryInterface $repository, UserSessionRepositoryInterface $sessionRepository, UserManagementPolicy $policy): Response
    {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);
        $user = $repository->findById($id);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }

        $actor = $this->requireUser();
        if (!$policy->canView($actor, $user)) {
            throw $this->createNotFoundException('User not found.');
        }

        $sessionPage = max(1, $request->query->getInt('session_page', 1));
        $sessionPerPage = 3;
        $sessionTotal = $sessionRepository->countByUser($user);
        $sessionTotalPages = max(1, (int) ceil($sessionTotal / $sessionPerPage));
        if ($sessionPage > $sessionTotalPages) {
            $sessionPage = $sessionTotalPages;
        }

        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'sessions' => $sessionRepository->findByUserPaginated($user, $sessionPage, $sessionPerPage),
            'sessionPagination' => [
                'page' => $sessionPage,
                'totalPages' => $sessionTotalPages,
                'total' => $sessionTotal,
                'perPage' => $sessionPerPage,
            ],
            'can_mutate' => $policy->canMutate($actor, $user),
            'can_switch_user' => $policy->canSwitchTo($actor, $user),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        UserRepositoryInterface $repository,
        UserManagementService $service,
        CsrfTokenManagerInterface $csrf,
        UserManagementPolicy $policy,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);
        $actor = $this->requireUser();
        $user = $repository->findById($id);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }
        if (!$policy->canMutate($actor, $user)) {
            throw $this->createNotFoundException('User not found.');
        }

        $data = [
            'username' => $user->getUsername(),
            'role' => $user->getRoles()[0] ?? UserRole::USER->value,
        ];
        $error = null;

        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, $csrf, self::CSRF_FORM);
            $data['username'] = trim((string) $request->request->get('username', $user->getUsername()));
            $data['role'] = (string) $request->request->get('role', UserRole::USER->value);
            $role = UserRole::tryFrom($data['role']);

            if ($role === null) {
                $error = 'Invalid role.';
            } elseif (!$policy->canCreateRole($actor, $role)) {
                $error = 'You cannot grant this role.';
            } else {
                try {
                    $service->update($actor, $id, $data['username'], $role, $request->getClientIp(), $request->headers->get('User-Agent'));
                    $this->addFlash('success', 'User updated successfully.');
                    return $this->redirectToRoute('admin_users_show', ['id' => $id]);
                } catch (\InvalidArgumentException|\DomainException|\RuntimeException $e) {
                    $error = $e->getMessage();
                } catch (\Throwable) {
                    $error = 'Unable to update user.';
                }
            }
        }

        $response = $this->render('admin/user/form.html.twig', [
            'mode' => 'edit',
            'user' => $user,
            'data' => $data,
            'roles' => $policy->manageableRoles($actor),
            'error' => $error,
        ]);
        if ($request->isMethod('POST')) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $response;
    }

    #[Route('/{id<\d+>}/status', name: 'admin_users_status', methods: ['POST'])]
    public function status(
        int $id,
        Request $request,
        UserManagementService $service,
        UserRepositoryInterface $repository,
        CsrfTokenManagerInterface $csrf,
        UserManagementPolicy $policy,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);
        $this->assertCsrf($request, $csrf, self::CSRF_STATE);
        $actor = $this->requireUser();
        $target = $repository->findById($id);
        if (!$target instanceof User) { throw $this->createNotFoundException('User not found.'); }
        if (!$policy->canMutate($actor, $target)) { throw $this->createNotFoundException('User not found.'); }
        $active = $request->request->getBoolean('active');

        try {
            $service->setActive($actor, $id, $active, $request->getClientIp(), $request->headers->get('User-Agent'));
            $this->addFlash('success', $active ? 'User activated successfully.' : 'User deactivated successfully.');
        } catch (\DomainException|\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to change user status.');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $id]);
    }

    #[Route('/{id<\d+>}/password', name: 'admin_users_password', methods: ['POST'])]
    public function password(
        int $id,
        Request $request,
        UserManagementService $service,
        UserRepositoryInterface $repository,
        CsrfTokenManagerInterface $csrf,
        UserManagementPolicy $policy,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::USER_MANAGE->value);
        $this->assertCsrf($request, $csrf, self::CSRF_PASSWORD);
        $actor = $this->requireUser();
        $target = $repository->findById($id);
        if (!$target instanceof User) { throw $this->createNotFoundException('User not found.'); }
        if (!$policy->canMutate($actor, $target)) { throw $this->createNotFoundException('User not found.'); }
        $password = (string) $request->request->get('password', '');
        $confirmation = (string) $request->request->get('password_confirmation', '');

        try {
            if ($password !== $confirmation) {
                throw new \InvalidArgumentException('Password confirmation does not match.');
            }
            $service->resetPassword($actor, $id, $password, $request->getClientIp(), $request->headers->get('User-Agent'));
            $this->addFlash('success', 'Password reset successfully.');
        } catch (\InvalidArgumentException|\DomainException|\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to reset password.');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $id]);
    }

    #[Route('/{id<\d+>}/sessions/{sessionId<\d+>}/revoke', name: 'admin_users_session_revoke', methods: ['POST'])]
    public function revokeSession(
        int $id,
        int $sessionId,
        Request $request,
        UserManagementService $service,
        UserRepositoryInterface $repository,
        CsrfTokenManagerInterface $csrf,
        UserManagementPolicy $policy,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SESSION_REVOKE->value);
        $this->assertCsrf($request, $csrf, self::CSRF_STATE);
        $actor = $this->requireUser();
        $target = $repository->findById($id);
        if (!$target instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }
        if (!$policy->canMutate($actor, $target)) {
            throw $this->createNotFoundException('User not found.');
        }

        try {
            $service->revokeSession($actor, $id, $sessionId, $request->getClientIp(), $request->headers->get('User-Agent'));
            $this->addFlash('success', 'Session revoked successfully.');
        } catch (\DomainException|\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to revoke session.');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $id]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }
        return $user;
    }

    private function assertCsrf(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }


}
