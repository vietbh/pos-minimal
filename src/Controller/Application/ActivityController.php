<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ActivityController extends AbstractController
{
    #[Route('/app/activity/heartbeat', name: 'app_activity_heartbeat', methods: ['POST'])]
    public function heartbeat(
        Request $request,
        CsrfTokenManagerInterface $csrf,
        UserSessionRepositoryInterface $sessions,
        TransactionManagerInterface $transactions,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$csrf->isTokenValid(new CsrfToken(
            'activity_heartbeat',
            (string) $request->request->get('_token', ''),
        ))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$user->isActive() || !$request->hasSession()) {
            return new JsonResponse(['ok' => false], 401);
        }

        $identifier = trim($request->getSession()->getId());
        if ($identifier === '') {
            return new JsonResponse(['ok' => false], 409);
        }

        $session = $sessions->findBySessionIdentifier($identifier);
        if ($session === null || !$session->isActive()) {
            return new JsonResponse(['ok' => false], 401);
        }

        $transactions->run(function ($context) use ($session, $request): void {
            $session->recordHeartbeat();
            $session->setIpAddress($request->getClientIp());
            $context->flush();
        });

        return new JsonResponse(['ok' => true]);
    }
}
