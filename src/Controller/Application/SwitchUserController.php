<?php

declare(strict_types=1);

namespace App\Controller\Application;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SwitchUserController extends AbstractController
{
    #[Route('/app/switch-user/exit', name: 'app_switch_user_exit', methods: ['GET'])]
    public function exit(): Response
    {
        // Symfony's native switch_user listener processes ?_switch_user=_exit
        // before this controller. The original token is restored there; this
        // route only provides a stable post-exit destination.
        return $this->redirectToRoute('app_home');
    }
}
