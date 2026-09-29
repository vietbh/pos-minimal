<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

final readonly class UserSessionActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserSessionRepositoryInterface $sessions,
        private TransactionManagerInterface $transactions,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
            KernelEvents::REQUEST => [
                ['onEarlyRequest', 100],
                ['onRequest', -20],
            ],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $sessionIdentifier = trim($request->getSession()->getId());
        if ($sessionIdentifier === '') {
            return;
        }

        $this->transactions->run(function ($context) use ($user, $request, $sessionIdentifier): void {
            $session = $this->sessions->findBySessionIdentifier($sessionIdentifier);
            if (!$session instanceof UserSession || !$session->isActive()) {
                $session = new UserSession($sessionIdentifier);
                $session->assignUser($user);
                $session->setIpAddress($request->getClientIp());
                $session->setUserAgent($this->truncate($request->headers->get('User-Agent'), 500));
                $session->setDevice($this->detectDevice($request->headers->get('User-Agent')));
                $this->sessions->save($session);
            }
            $context->flush();
        });
    }

    public function onEarlyRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $sessionIdentifier = trim($request->getSession()->getId());
        if ($sessionIdentifier === '') {
            return;
        }

        $session = $this->sessions->findBySessionIdentifier($sessionIdentifier);
        if (!$session instanceof UserSession || $session->isActive()) {
            return;
        }

        $request->getSession()->invalidate();
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') === 'app_activity_heartbeat') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // A native Symfony SwitchUserToken is an overlay on the physical
        // browser session. The UserSession belongs to the original actor,
        // not to the impersonated target. Track activity against that
        // physical owner so exiting switch_user never destroys/invalidates
        // the original account session.
        if ($token instanceof SwitchUserToken) {
            $user = $token->getOriginalToken()->getUser();
        }

        if (!$user instanceof User || !$user->isActive() || !$request->hasSession()) {
            return;
        }

        $sessionIdentifier = trim($request->getSession()->getId());
        if ($sessionIdentifier === '') {
            return;
        }

        $session = $this->sessions->findBySessionIdentifier($sessionIdentifier);
        if (!$session instanceof UserSession || !$session->isActive()) {
            return;
        }

        if ($session->getUser()->getId() !== $user->getId()) {
            return;
        }

        $this->transactions->run(function ($context) use ($session, $request): void {
            $session->recordRequest(
                $request->getMethod(),
                $request->getPathInfo(),
                $request->getClientIp(),
            );
            $context->flush();
        });
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $sessionIdentifier = trim($request->getSession()->getId());
        if ($sessionIdentifier === '') {
            return;
        }

        $session = $this->sessions->findBySessionIdentifier($sessionIdentifier);
        if (!$session instanceof UserSession || !$session->isActive()) {
            return;
        }

        $this->transactions->run(function ($context) use ($session): void {
            $session->logout();
            $this->sessions->save($session);
            $context->flush();
        });
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return mb_substr(trim($value), 0, $max);
    }

    private function detectDevice(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'Tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'Mobile';
        }
        return 'Desktop';
    }
}
