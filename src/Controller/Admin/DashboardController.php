<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Entity\Photo;
use App\Entity\Survey;
use App\Entity\SurveyChoice;
use App\Entity\Theme;
use App\Entity\User;
use App\Form\SurveyChoiceType;
use App\Form\SurveyType;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/admin/dashboard', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', 'dashboard')]
    public function index(): Response
    {
        $actualSurvey = $this->em->getRepository(Survey::class)->findActiveSurvey();
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        $contacts = $this->em->getRepository(Contact::class)->findBy(['type' => Contact::TYPE_CONTACT, 'isView' => false]);
        $photosNotVisible = $this->em->getRepository(Photo::class)->findBy(['is_visible' => false, 'theme' => $actualTheme]);
        $themes = $this->em->getRepository(SurveyChoice::class)->findAll();

        return $this->render('admin/dashboard/index.html.twig', [
            'survey' => $actualSurvey,
            'contacts' => $contacts,
            'photosNotVisible' => $photosNotVisible,
            'themes' => $themes,
        ]);
    }

    #[Route('/manage/survey/{id}', 'manage_survey', requirements: ['id' => "\d+"], defaults: ['id' => null])]
    public function manageSurveys(
        Request $request,
        #[MapEntity(mapping: ['id' => 'id'])] ?Survey $survey = null,
    ): Response {
        $survey ??= new Survey();
        $surveyChoice = new SurveyChoice();
        $surveys = $this->em->getRepository(Survey::class)->findAll();
        $surveyChoices = $this->em->getRepository(SurveyChoice::class)->findBy(['survey' => null]);

        $formSurvey = $this->createForm(SurveyType::class, $survey);
        $formSurvey->handleRequest($request);

        if ($formSurvey->isSubmitted() && $formSurvey->isValid()) {
            $this->em->persist($survey);
            $this->em->flush();

            $this->addFlash('alert-success', 'le sondage a été ajouté avec succes');

            return $this->redirectToRoute('admin_manage_survey');
        }

        $formSurveyChoice = $this->createForm(SurveyChoiceType::class, $surveyChoice);
        $formSurveyChoice->handleRequest($request);

        if ($formSurveyChoice->isSubmitted() && $formSurveyChoice->isValid()) {
            $surveyChoice->setNumberVoter(0);
            $this->em->persist($surveyChoice);
            $this->em->flush();

            $this->addFlash('alert-success', 'le theme a été ajouté avec succes');

            return $this->redirectToRoute('admin_manage_survey');
        }

        return $this->render('admin/dashboard/manageSurveys.html.twig', [
            'surveys' => $surveys,
            'surveyChoices' => $surveyChoices,
            'formSurvey' => $formSurvey,
            'formSurveyChoice' => $formSurveyChoice,
        ]);
    }

    #[Route('/manage/delete/survey/{id}', 'manage_survey_delete', methods: ['DELETE'])]
    public function deleteSurvey(Request $request, Survey $survey): Response
    {
        if ($this->isCsrfTokenValid('delete-survey-' . $survey->getId(), $request->request->get('_token'))) {
            $this->em->remove($survey);
            $this->em->flush();

            $this->addFlash('alert-success', 'Sondage supprimé avec succes');
        }

        return $this->redirectToRoute('admin_manage_survey');
    }

    #[Route('/manage/delete/surveychoice/{id}', 'delete_surveyChoice', methods: ['DELETE'])]
    public function deleteSurveyChoice(Request $request, SurveyChoice $surveyChoice): Response
    {
        if ($this->isCsrfTokenValid('delete-survey-choice-' . $surveyChoice->getId(), $request->request->get('_token'))) {
            $this->em->remove($surveyChoice);
            $this->em->flush();

            $this->addFlash('alert-success', 'Theme supprimé avec succes');
        }

        return $this->redirectToRoute('admin_manage_survey');
    }

    #[Route('/contact/reply/{id}', 'contact_reply', methods: ['GET', 'POST'])]
    public function replyContact(
        Contact $contact,
        Request $request,
        MailService $mailService,
        LoggerInterface $logger,
        #[Autowire('%app.admin_email%')] string $adminEmail,
    ): Response {
        if ($request->isMethod('POST') && $request->request->all('reply')) {
            $form = $request->request->all('reply');
            $message = trim((string) ($form['message'] ?? ''));

            if (!$this->isCsrfTokenValid('reply_contact_' . $contact->getId(), (string) ($form['csrf'] ?? '')) || '' === $message) {
                $this->addFlash('error', 'Le formulaire n\'est pas valide');

                return $this->redirectToRoute('admin_contact_reply', ['id' => $contact->getId()]);
            }
            $contact->setIsView(true);
            $replyContact = new Contact();

            $user = $this->getUser();
            $replyContact
                ->setEmail($adminEmail)
                ->setMessage($message)
                ->setOrigin((int) $contact->getId())
                ->setUserId($user instanceof User ? $user->getId() : null)
                ->setIsView(true)
                ->setType(Contact::TYPE_REPLY)
            ;

            try {
                $this->em->persist($replyContact);
                $this->em->flush();
            } catch (\Exception $exception) {
                $logger->error($exception->getMessage());
                $this->addFlash('error', 'Erreur lors de l\'enregistrement de la réponse');

                return $this->redirectToRoute('admin_contact_reply', ['id' => $contact->getId()]);
            }

            $discution[] = $contact;
            $discution[] = $replyContact;

            $mailService->sendEmail(
                new TemplatedEmail()
                    ->to((string) $contact->getEmail())
                    ->subject('Formulaire de contact')
                    ->htmlTemplate('contact/replyContactTmpl.html.twig'),
                $discution,
            );

            $this->addFlash('success', 'Réponse envoyée');

            // Post/Redirect/Get : évite un double envoi si l'admin rafraîchit la page
            return $this->redirectToRoute('admin_contact_reply', ['id' => $contact->getId()]);
        }

        return $this->render('admin/dashboard/replyContact.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/contact/read/{id}', 'contact_read', methods: ['GET'])]
    public function readContact(Contact $contact): Response
    {
        $contact->setIsView(true);
        $this->em->flush();

        $this->addFlash('success', 'Message marqué comme vue');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/contact/show', 'contact_all')]
    public function showMessages(): Response
    {
        $contacts = $this->em->getRepository(Contact::class)->findAll();

        return $this->render('admin/dashboard/showMessages.html.twig', [
            'contacts' => $contacts,
        ]);
    }

    #[Route('/contact/delete', 'contact_delete', methods: ['DELETE'])]
    public function deleteMessages(Request $request): Response
    {
        $form = $request->request->all('message');

        $contact = $this->em->getRepository(Contact::class)->find((int) ($form['id'] ?? 0));
        if (!$contact) {
            $this->addFlash('danger', 'Message introuvable');

            return $this->redirectToRoute('admin_contact_all');
        }

        if (!$this->isCsrfTokenValid('delete_message_' . $contact->getId(), (string) ($form['csrf'] ?? ''))) {
            $this->addFlash('danger', 'csrf non valide');
        } else {
            $this->em->remove($contact);
            $this->em->flush();

            $this->addFlash('success', 'Message supprimé avec succes');
        }

        return $this->redirectToRoute('admin_contact_all');
    }

    #[Route('/utils', 'utils')]
    public function showUtils(): Response
    {
        return $this->render('admin/dashboard/doc.html.twig');
    }

    #[Route('/theme/enable/{id}', name: 'theme_enable', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"csrf-token-" ~ args["choice"].getId()'))]
    public function enableTheme(Request $request, SurveyChoice $choice): Response
    {
        $theme = new Theme();
        $theme
            ->setThemeChoice($choice)
            ->setDateStart(new \DateTime())
            ->setDateEnd(new \DateTime()->modify('+ 30 days'))
            ->setDirectoryName(uniqid())
            ->setIsAlreadyActived(true)
        ;

        $this->em->persist($theme);
        $this->em->flush();

        return $this->redirectToRoute('admin_dashboard');
    }
}
