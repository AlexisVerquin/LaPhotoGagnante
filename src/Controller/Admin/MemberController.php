<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\DataEraser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modération des membres : suspendre, réactiver ou supprimer un compte (CGU § 7).
 */
#[Route('/admin/dashboard/members', name: 'admin_members')]
final class MemberController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/dashboard/members.html.twig', [
            'members' => $this->em->getRepository(User::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/suspend', name: '_suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleSuspension(User $member, Request $request): Response
    {
        if ($response = $this->refuse($member, $request, 'suspend-member-')) {
            return $response;
        }

        $member->setIsActive(!$member->isActive());
        $this->em->flush();

        $this->addFlash('success', sprintf(
            $member->isActive() ? 'Le compte de %s est réactivé.' : 'Le compte de %s est suspendu : il ne peut plus se connecter.',
            $member->getName(),
        ));

        return $this->redirectToRoute('admin_members');
    }

    #[Route('/{id}/delete', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $member, Request $request, DataEraser $dataEraser): Response
    {
        if ($response = $this->refuse($member, $request, 'delete-member-')) {
            return $response;
        }

        $name = $member->getName();
        $dataEraser->eraseAccount($member);
        $this->addFlash('success', sprintf('Le compte de %s et toutes ses données ont été supprimés.', $name));

        return $this->redirectToRoute('admin_members');
    }

    /**
     * Jeton CSRF invalide, ou admin qui agit sur son propre compte (il se bloquerait lui-même).
     */
    private function refuse(User $member, Request $request, string $tokenPrefix): ?Response
    {
        if (!$this->isCsrfTokenValid($tokenPrefix . $member->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide');

            return $this->redirectToRoute('admin_members');
        }

        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $member->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas suspendre ou supprimer votre propre compte d\'ici.');

            return $this->redirectToRoute('admin_members');
        }

        return null;
    }
}
