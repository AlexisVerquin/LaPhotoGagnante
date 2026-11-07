<?php

namespace App\Controller\Admin;

use App\Entity\Theme;
use App\Form\ThemeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/dashboard/theme', name: 'admin_')]
class ThemeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/{id}', 'manage_themes', requirements: ['id' => "\d+"], defaults: ['id' => null])]
    public function manageThemes(
        Request $request,
        #[MapEntity(mapping: ['id' => 'id'])] ?Theme $theme = null,
    ): Response {
        if (!$theme) {
            $theme = new Theme()
                ->setDateStart(new \DateTime('first day of this month'))
                ->setDateEnd(new \DateTime('last day of this month'))
                ->setDirectoryName(uniqid())
                ->setIsAlreadyActived(false)
            ;
        }

        $formTheme = $this->createForm(ThemeType::class, $theme);
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();

        $formTheme->handleRequest($request);
        if ($formTheme->isSubmitted() && $formTheme->isValid()) {
            if (!$theme->getId()) {
                $this->em->persist($theme);
                $message = 'Theme successfully added';
            } else {
                $message = 'Theme successfully updated';
            }

            $this->em->flush();
            $this->addFlash('alert-success', $message);

            return $this->redirectToRoute('admin_manage_themes');
        }

        return $this->render('admin/dashboard/manageThemes.html.twig', [
            'formTheme' => $formTheme,
            'actualTheme' => $actualTheme,
        ]);
    }
}
