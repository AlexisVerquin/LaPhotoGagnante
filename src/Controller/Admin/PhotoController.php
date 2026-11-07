<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Photo;
use App\Service\DataEraser;
use App\Service\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/photo', name: 'app_admin_photo')]
class PhotoController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/accept/{id}', name: '_accept', methods: ['POST'])]
    public function acceptPhoto(Request $request, Photo $photo, PhotoStorage $photoStorage): Response
    {
        if (!$this->isCsrfTokenValid('accept-photo-' . $photo->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide');

            return $this->redirectToRoute('admin_dashboard');
        }

        try {
            // La photo validée sort de la zone privée et devient visible de tous.
            $photoStorage->publish($photo);
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_dashboard');
        }

        $photo->setIsVisible(true);

        $this->em->flush();
        $this->addFlash('success', 'Photo validée avec succès');

        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Refuser une photo en attente ou retirer une photo publiée : dans les deux cas elle est supprimée avec ses votes.
     */
    #[Route('/delete/{id}', name: '_delete', methods: ['POST'])]
    public function deletePhoto(Request $request, Photo $photo, DataEraser $dataEraser): Response
    {
        // On revient là d'où l'admin a cliqué (tableau de bord ou galerie), jamais vers une adresse arbitraire
        $back = 'app_gallery' === $request->request->getString('_back') ? 'app_gallery' : 'admin_dashboard';

        if (!$this->isCsrfTokenValid('delete-photo-' . $photo->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide');

            return $this->redirectToRoute($back);
        }

        $wasVisible = (bool) $photo->isVisible();
        $dataEraser->erasePhoto($photo);
        $this->addFlash('success', $wasVisible ? 'Photo retirée du concours.' : 'Photo refusée.');

        return $this->redirectToRoute($back);
    }
}
