<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ImpersonationExtension extends AbstractExtension
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_impersonating', [$this, 'isImpersonating']),
            new TwigFunction('impersonated_username', [$this, 'impersonatedUsername']),
            new TwigFunction('impersonator_username', [$this, 'impersonatorUsername']),
        ];
    }

    public function isImpersonating(): bool
    {
        return $this->tokenStorage->getToken() instanceof SwitchUserToken;
    }

    public function impersonatedUsername(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        $user = $token->getUser();
        return method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
    }

    public function impersonatorUsername(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        $user = $token->getOriginalToken()->getUser();
        return method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
    }
}
