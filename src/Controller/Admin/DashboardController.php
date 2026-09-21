<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Entity\Survey;
use App\Entity\SurveyChoice;
use App\Form\SurveyChoiceType;
use App\Form\SurveyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/admin/dashboard", name: "admin_")]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ){}

    #[Route("", "dashboard")]
    public function index(): Response {
        $actualSurvey = $this->em->getRepository(Survey::class)->findOneBy( ['id' => 'ASC']);
        $contacts = $this->em->getRepository(Contact::class)->findAll();

        return $this->render('admin/dashboard/index.html.twig', [
            'survey' => $actualSurvey,
            'contacts' => $contacts,
        ]);
    }

    #[Route("/manage/survey/{id}", "manage_survey", requirements: ["id" => "\d+"], defaults: ["id" => null])]
    public function manageSurveys(
        Request $request,
        #[MapEntity(mapping: ["id" => "id"])] ?Survey $survey = null
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

    #[Route("/manage/delete/survey/{id}", "manage_survey_delete", methods: ["DELETE"])]
    public function deleteSurvey(Request $request, Survey $survey): Response {
        if($this->isCsrfTokenValid('delete-survey-'.$survey->getId(), $request->request->get('_token'))) {
            $this->em->remove($survey);
            $this->em->flush();

            $this->addFlash('alert-success', 'Sondage supprimé avec succes');
        }

        return $this->redirectToRoute('admin_manage_survey');
    }

    #[Route("/manage/delete/surveychoice/{id}", "delete_surveyChoice", methods: ["DELETE"])]
    public function deleteSurveyChoice(Request $request, SurveyChoice $surveyChoice): Response {
        if ($this->isCsrfTokenValid('delete-survey-choice-' . $surveyChoice->getId(), $request->request->get('_token'))) {
            $this->em->remove($surveyChoice);
            $this->em->flush();

            $this->addFlash('alert-success', 'Theme supprimé avec succes');
        }

        return $this->redirectToRoute('admin_manage_survey');
    }
}
