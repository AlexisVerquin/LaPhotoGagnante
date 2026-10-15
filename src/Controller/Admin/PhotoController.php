<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/photo', name: 'app_admin_photo')]
class PhotoController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/accept/{id}', name: '_accept')]
    public function acceptPhoto(Photo $photo): Response
    {
        $photo->setIsVisible(true);

        $this->em->flush();
        $this->addFlash('success', 'photo mis a jour avec succés');

        return $this->redirectToRoute('admin_dashboard');
    }
}
