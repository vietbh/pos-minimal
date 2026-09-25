<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Security\Permission;
use App\Domain\SalesPoint\Enum\SalesPointType;
use App\Domain\SalesPoint\Repository\SalesPointGroupRepositoryInterface;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\SalesPointGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/sales-points')]
final class SalesPointController extends AbstractController
{
    private const POINT_CSRF = 'sales_point';
    private const POINT_STATE_CSRF = 'sales_point_state';
    private const GROUP_CSRF = 'sales_point_group';
    private const GROUP_STATE_CSRF = 'sales_point_group_state';

    #[Route('', name: 'admin_sales_points', methods: ['GET'])]
    public function index(
        Request $request,
        SalesPointRepositoryInterface $points,
        SalesPointGroupRepositoryInterface $groups,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);

        $editPoint = $request->query->getInt('edit', 0);
        $editGroup = $request->query->getInt('edit_group', 0);

        return $this->render('admin/sales_point/index.html.twig', [
            'sales_points' => $points->findAll(),
            'sales_point_groups' => $groups->findAll(),
            'edit_sales_point' => $editPoint > 0 ? $points->findById($editPoint) : null,
            'edit_sales_point_group' => $editGroup > 0 ? $groups->findById($editGroup) : null,
            'types' => SalesPointType::cases(),
            'point_csrf_token' => $csrf->getToken(self::POINT_CSRF)->getValue(),
            'point_state_csrf_token' => $csrf->getToken(self::POINT_STATE_CSRF)->getValue(),
            'group_csrf_token' => $csrf->getToken(self::GROUP_CSRF)->getValue(),
            'group_state_csrf_token' => $csrf->getToken(self::GROUP_STATE_CSRF)->getValue(),
        ]);
    }

    #[Route('/create', name: 'admin_sales_point_create', methods: ['POST'])]
    public function createPoint(
        Request $request,
        SalesPointRepositoryInterface $points,
        SalesPointGroupRepositoryInterface $groups,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
        $this->assertCsrf($csrf, self::POINT_CSRF, $request);

        try {
            $id = $request->request->getInt('id', 0);
            $point = $id > 0 ? $points->findById($id) : null;
            $code = trim((string) $request->request->get('code', ''));
            $name = trim((string) $request->request->get('name', ''));
            $type = SalesPointType::tryFrom(strtoupper(trim((string) $request->request->get('type', 'POS'))));
            $groupId = $request->request->getInt('group_id', 0);
            $group = $groupId > 0 ? $groups->findById($groupId) : null;

            if ($type === null) {
                throw new \InvalidArgumentException('Invalid sales point type.');
            }
            if ($groupId > 0 && $group === null) {
                throw new \InvalidArgumentException('Sales point group not found.');
            }
            if ($group !== null && !$group->isActive()) {
                throw new \InvalidArgumentException('Sales point group is inactive.');
            }
            if ($points->existsByCode($code, $point?->getId())) {
                throw new \InvalidArgumentException('Sales point code already exists.');
            }

            if ($point instanceof SalesPoint) {
                $point->update($code, $name, $type, $group, $request->request->getBoolean('is_active', true));
            } else {
                $point = new SalesPoint($code, $name, $type, $group, true);
            }
            $points->save($point);
            $this->addFlash('success', 'Sales point saved.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Unable to save sales point.');
        }

        return $this->redirectToRoute('admin_sales_points');
    }

    #[Route('/groups/create', name: 'admin_sales_point_group_create', methods: ['POST'])]
    public function createGroup(
        Request $request,
        SalesPointGroupRepositoryInterface $groups,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
        $this->assertCsrf($csrf, self::GROUP_CSRF, $request);

        try {
            $id = $request->request->getInt('id', 0);
            $group = $id > 0 ? $groups->findById($id) : null;
            $code = trim((string) $request->request->get('code', ''));
            $name = trim((string) $request->request->get('name', ''));
            $sortOrder = max(0, $request->request->getInt('sort_order', 0));
            $active = $request->request->getBoolean('is_active', true);

            if ($groups->existsByCode($code, $group?->getId())) {
                throw new \InvalidArgumentException('Sales point group code already exists.');
            }

            if ($group instanceof SalesPointGroup) {
                $group->update($code, $name, $sortOrder, $active);
            } else {
                $group = new SalesPointGroup($code, $name, $sortOrder);
            }
            $groups->save($group);
            $this->addFlash('success', 'Sales point group saved.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Unable to save sales point group.');
        }

        return $this->redirectToRoute('admin_sales_points');
    }

    #[Route('/groups/{id<\d+>}/toggle', name: 'admin_sales_point_group_toggle', methods: ['POST'])]
    public function toggleGroup(
        int $id,
        Request $request,
        SalesPointGroupRepositoryInterface $groups,
        CsrfTokenManagerInterface $csrf,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
        $this->assertCsrf($csrf, self::GROUP_STATE_CSRF, $request);
        $group = $groups->findById($id);
        if (!$group instanceof SalesPointGroup) {
            throw $this->createNotFoundException('Sales point group not found.');
        }
        $group->update($group->getCode(), $group->getName(), $group->getSortOrder(), !$group->isActive());
        $groups->save($group);
        $this->addFlash('success', $translator->trans($group->isActive() ? 'sales_point.group_enabled' : 'sales_point.group_disabled'));
        return $this->redirectToRoute('admin_sales_points');
    }

    #[Route('/{id<\d+>}/toggle', name: 'admin_sales_point_toggle', methods: ['POST'])]
    public function toggle(
        int $id,
        Request $request,
        SalesPointRepositoryInterface $points,
        CsrfTokenManagerInterface $csrf,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(Permission::SALES_POINT_MANAGE->value);
        $this->assertCsrf($csrf, self::POINT_STATE_CSRF, $request);
        $point = $points->findById($id);
        if (!$point instanceof SalesPoint) {
            throw $this->createNotFoundException('Sales point not found.');
        }
        $point->setActive(!$point->isActive());
        $points->save($point);
        $this->addFlash('success', $translator->trans($point->isActive() ? 'sales_point.enabled' : 'sales_point.disabled'));
        return $this->redirectToRoute('admin_sales_points');
    }

    private function assertCsrf(CsrfTokenManagerInterface $csrf, string $id, Request $request): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
