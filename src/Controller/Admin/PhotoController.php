<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Photo;
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
}
