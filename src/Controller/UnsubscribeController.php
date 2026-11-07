<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Désinscription des emails « nouveau concours » depuis le lien de l'email, sans se connecter.
 */
final class UnsubscribeController extends AbstractController
{
    #[Route('/desinscription/{id}', name: 'app_unsubscribe', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function unsubscribe(User $user, Request $request, UriSigner $uriSigner, EntityManagerInterface $em): Response
    {
        // Sans signature valide, le lien a été modifié ou inventé
        if (!$uriSigner->checkRequest($request)) {
            throw $this->createNotFoundException();
        }

        // Un simple clic (GET) demande confirmation : certains antivirus « visitent » les liens des emails.
        // La désinscription se fait en POST, depuis la page ou directement par Gmail / Outlook.
        if ($request->isMethod('POST')) {
            $user->setNotifyNewTheme(false);
            $em->flush();

            return $this->render('unsubscribe/done.html.twig');
        }

        return $this->render('unsubscribe/confirm.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }
}
