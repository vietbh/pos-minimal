<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\UserManagementPolicy;
use App\Domain\User\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final readonly class SwitchUserSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private UserManagementPolicy $policy,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::SWITCH_USER => ['onSwitchUser', 100],
            KernelEvents::REQUEST => ['onRequest', -30],
        ];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $target = $event->getTargetUser();
        $token = $event->getToken();

        if (!$target instanceof User || !$token instanceof SwitchUserToken) {
            return;
        }

        $originalUser = $token->getOriginalToken()->getUser();
        if (!$originalUser instanceof User) {
            throw new AccessDeniedException('Invalid impersonation source.');
        }

        if (!$this->policy->canSwitchTo($originalUser, $target)) {
            throw new AccessDeniedException('You are not allowed to switch to this account.');
        }
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof SwitchUserToken) {
            return;
        }

        $target = $token->getUser();
        if (!$target instanceof User || $target->isActive()) {
            return;
        }

        $originalToken = $token->getOriginalToken();
        $originalUser = $originalToken->getUser();
        if (!$originalUser instanceof User || !$originalUser->isActive()) {
            $this->tokenStorage->setToken(null);
            if ($event->getRequest()->hasSession()) {
                $event->getRequest()->getSession()->invalidate();
            }

            return;
        }

        $this->tokenStorage->setToken($originalToken);
        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_home'),
        ));
        $event->stopPropagation();
    }
}
