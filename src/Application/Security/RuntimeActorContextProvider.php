<?php

declare(strict_types=1);

namespace App\Application\Security;

final class RuntimeActorContextProvider implements ActorContextProviderInterface
{
    private ?ActorContext $context = null;

    public function set(ActorContext $context): void
    {
        $this->context = $context;
    }

    public function get(): ActorContext
    {
        if ($this->context === null) {
            throw new \LogicException(
                'Actor context has not been initialized for the current execution.',
            );
        }

        return $this->context;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
