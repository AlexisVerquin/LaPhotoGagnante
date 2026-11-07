<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    private const SUCCESS_MESSAGE = 'Message envoyé, merci !';

    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        RateLimiterFactoryInterface $contactFormLimiter,
    ): Response {
        $contact = new Contact();
        $user = $this->getUser();
        if ($user instanceof User && $user->getEmail()) {
            // Pré-remplit l'email du membre connecté (il reste modifiable)
            $contact->setEmail($user->getEmail());
        }
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Pot de miel rempli : c'est un robot. On fait comme si tout allait bien, sans rien enregistrer.
            if ('' !== trim((string) $form->get('website')->getData())) {
                $this->addFlash('success', self::SUCCESS_MESSAGE);

                return $this->redirectToRoute('app_contact');
            }

            $limit = $contactFormLimiter->create($request->getClientIp() ?? 'unknown')->consume();
            if (!$limit->isAccepted()) {
                $minutes = max(1, (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
                $this->addFlash('danger', sprintf('Trop de messages envoyés. Merci de réessayer dans %d minute(s).', $minutes));

                return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }

            $contact
                ->setType(Contact::TYPE_CONTACT)
                ->setOrigin(0)
                ->setUserId($user instanceof User ? $user->getId() : null)
            ;
            $entityManager->persist($contact);
            $entityManager->flush();
            $this->addFlash('success', self::SUCCESS_MESSAGE);

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
