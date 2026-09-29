<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UsageGuideController extends AbstractController
{
    #[Route('/app/guide', name: 'app_usage_guide', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser() instanceof User) {
            return $this->redirectToRoute('login');
        }

        return $this->render('application/usage_guide.html.twig');
    }
}
