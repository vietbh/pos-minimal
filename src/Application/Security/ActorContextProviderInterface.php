<?php

declare(strict_types=1);

namespace App\Application\Security;

interface ActorContextProviderInterface
{
    public function get(): ActorContext;
}
