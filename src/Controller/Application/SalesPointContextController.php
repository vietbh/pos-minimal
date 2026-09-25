<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Application\SalesPoint\CurrentSalesPointContext;
use App\Application\Security\Permission;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SalesPointContextController extends AbstractController
{
    #[Route('/app/pos/sales-point', name: 'pos_sales_point_select', methods: ['POST'])]
    public function select(Request $request, SalesPointRepositoryInterface $salesPoints, CurrentSalesPointContext $context, CsrfTokenManagerInterface $csrf): Response
    {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_SELECT->value);
        if (!$this->getUser() instanceof User) return $this->redirectToRoute('login');
        if (!$csrf->isTokenValid(new CsrfToken('pos_sales_point', (string) $request->request->get('_token')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
        $id = $request->request->getInt('sales_point_id');
        $point = $id > 0 ? $salesPoints->findById($id) : null;
        if ($point === null || !$point->isActive()) throw $this->createAccessDeniedException('Sales point is not available.');
        $context->setId($id);
        $this->addFlash('success', 'Điểm bán đã được chọn: '.$point->getName().'.');
        return $this->redirectToRoute('pos');
    }
}
