<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Survey;
use App\Entity\SurveyChoice;
use App\Entity\Theme;
use App\Entity\User;
use App\Entity\Vote;
use App\Service\PhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Constant\AlertConst;
use Symfony\Component\Translation\LocaleFallbackProvider;

final class MainController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {}
    #[Route('/', name: 'app_main')]
    public function index(Request $request, PhotoService $photoService): Response
    {
        $locale = $request->getPreferredLanguage(['pt', 'fr_Latn_CH', 'en_US']);
        LocaleFallbackProvider::validateLocale($locale);

        $themeRepository = $this->em->getRepository(Theme::class);
        $actualTheme = $themeRepository->findActualTheme();
        $hasPhoto = $this->em->getRepository(Photo::class)->findOneBy(['user' => $this->getUser(), 'theme' => $actualTheme]);
        $activeSurvey = $this->em->getRepository(Survey::class)->findActiveSurvey();

        $nbPhoto = $this->em->getRepository(Photo::class)->count();
        $nbUsers = $this->em->getRepository(User::class)->count();
        $nbVotes = $this->em->getRepository(Vote::class)->count();
        $nbThemes = $this->em->getRepository(Theme::class)->count();

        $totalVote = 0;
        if ($activeSurvey) {
            foreach ($activeSurvey->getSurveyChoices() as $survey) {
                $totalVote += $survey->getNumberVoter();
            }
        }

        $lastTheme = $themeRepository->findLastFinishedTheme();
        $lastWinner = $photoService->getPhotoWinnerLastTheme();
        $photoOfTheDay = $photoService->getPhotoOfTheDay();


        if ($this->getUser() && !$this->getUser()->isVerified()) {
            $message = $this->translator->trans(AlertConst::ALERT_MSG_USER_UNVERIFIED);
            $this->addFlash('USER_NOT_VERIFIED', $message);
        }

        $today = new \DateTime();
        $lastVote = $this->getUser()?->getLastVoteDate() ?? null;

        return $this->render('main/index.html.twig', [
            'actualTheme' => $actualTheme,
            'lastTheme' => $lastTheme,
            'hasPhoto' => $hasPhoto,
            'activeSurvey' => $activeSurvey,
            'totalVoteSurvey' => $totalVote,
            'alreadyVoted' => $lastVote !== null && $lastVote->format('Y-m-d') === $today->format('Y-m-d'),
            'lastWinner' => $lastWinner,
            'photoOfTheDay' => $photoOfTheDay,
            'nbPhoto' => $nbPhoto ?? 0,
            'nbUsers' => $nbUsers ?? 0,
            'nbVotes' => $nbVotes ?? 0,
            'nbThemes' => $nbThemes ?? 0,
            'allThemes' => $themeRepository->findAll(),
        ]);
    }

    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(Request $request): Response
    {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        $votes = $this->em->getRepository(Vote::class)->findBy(['user' => $this->getUser(), 'theme' => $actualTheme]);

        $voteUser = null;
        foreach ($votes as $vote) {
            if ($vote->getTheme()->getId() === $actualTheme->getId()) {
                $voteUser = $vote;
                break;
            }
        }

        $dateForEnableVote = clone $actualTheme->getDateEnd();
        $dateForEnableVote->modify('-6 day');
        $today = new \DateTime();
        $enableVote = true;
        if ($dateForEnableVote->format('Y-m-d') > $today->format('Y-m-d')) {
            $enableVote = false;
        }

        return $this->render('main/gallery.html.twig', [
            'actualTheme' => $actualTheme,
            'voteUser' => $voteUser,
            'enableVote' => $enableVote,
        ]);
    }

    #[Route('/voteSurvey', name: 'app_main_vote')]
    public function voteSurvey(Request $request): JsonResponse
    {
        $submittedToken = $request->headers->get('X-CSRF-Token');

        if (!$this->isCsrfTokenValid('survey-vote', $submittedToken)) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_UNAUTHORIZED); // 401
        }

        $data = $request->request->get('id');

        if ($data === null) {
            return $this->json(['error' => "Une erreur est survenue pendant le vote, merci de réessayer"], Response::HTTP_BAD_REQUEST); // 400
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => "Vous devez être connecté pour pouvoir voter"], Response::HTTP_UNAUTHORIZED); // 401
        }

        if (!$user->isVerified()) {
            return $this->json(['error' => "Vous devez confirmer votre adresse email pour pouvoir voter"], Response::HTTP_FORBIDDEN); // 403
        }

        $today = new \DateTime();
        $lastVote = $user->getLastVoteDate();

        if ($lastVote !== null && $lastVote->format('Y-m-d') === $today->format('Y-m-d')) {
            return $this->json(['error' => "Vous avez déjà voté aujourd'hui, revenez demain"], Response::HTTP_CONFLICT); // 409
        }

        $surveyChoice = $this->em->getRepository(SurveyChoice::class)->find($data);

        if (!$surveyChoice) {
            return $this->json(['error' => "Ce choix de vote n'existe pas"], Response::HTTP_NOT_FOUND); // 404
        }

        $surveyChoice->setNumberVoter($surveyChoice->getNumberVoter() + 1);
        $user->setLastVoteDate($today);

        $this->em->flush();

        $total = array_sum(array_map(
            fn($choice) => $choice->getNumberVoter(),
            $surveyChoice->getSurvey()->getSurveyChoices()->toArray()
        ));

        return $this->json([
            'message' => "Merci d'avoir voté, n'hésitez pas à revenir demain",
            'vote' => $surveyChoice->getNumberVoter(),
            'alreadyVoted'=> true,
            'total' => $total,
        ]);
    }

    #[Route('/locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);
        return $this->redirectToRoute('app_main');
    }

    #[Route('/generate-data', name: 'app_dev_data')]
    public function generateData(): Response {
        $faker = \Faker\Factory::create();
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        for($i = 0; $i < 25; $i++) {
            $user = new User();
            $photo = new Photo();



            $user
                ->setName($faker->name)
                ->setEmail($faker->email)
                ->setPassword($faker->password())
                ->setIsVerified(true)
                ->setCity($faker->city)
                ->setRoles(['ROLE_USER'])
            ;

            $photo
                ->setName($faker->name."_photo")
                ->setIsVisible(true)
                ->setTheme($actualTheme)
                ->setUser($user)
            ;

            $this->em->persist($user);
            $this->em->persist($photo);
        }
        $this->em->flush();
        $this->em->clear();

        return $this->redirectToRoute('app_main');
    }

    #[Route('/vote', name: 'app_vote')]
    public function vote(Request $request): Response
    {
        //dump(app.request.attributes.parameters)
        $photo = $this->em->getRepository(Photo::class)->find($request->request->get('photoId'));
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();


        if (!$this->getUser()) {
            $cartUrl = $this->generateUrl('app_login');
            return $this->json(
                [
                    'success' => false,
                    'message' => sprintf(
                        'Vous devez etre connecté pour pouvoir voter. <a href="%s" class="alert-link">connectez-vous</a>',
                        $cartUrl
                    )
                ], 200
            );
        }

        $dateForEnableVote = clone $actualTheme->getDateEnd();
        $dateForEnableVote->modify('-6 day');
        $today = new \DateTime();
        if ($dateForEnableVote->format('Y-m-d') > $today->format('Y-m-d')) {
            return $this->json(
                [
                    'success' => false,
                    'message' => "Les votes seront activé le ".$dateForEnableVote->format('d-m-Y')
                ], 200
            );
        }


        if (!$photo) {
            return $this->json(['success' => false, 'message' => 'Photo introuvable'], 404);
        }

        $existingVote = $this->em->getRepository(Vote::class)->findOneBy([
            'user' => $this->getUser(),
            'theme' => $photo->getTheme(),
        ]);

        $oldPhotoId = null;

        if ($existingVote) {
            if ($existingVote->getPhoto() === $photo) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous avez déjà voté pour cette photo',
                ], 200);
            }

            $oldPhotoId = $existingVote->getPhoto()->getId();
            $existingVote
                ->setPhoto($photo)
                ->setDate(new \DateTime())
            ;
        } else {
            $existingVote = (new Vote())
                ->setPhoto($photo)
                ->setTheme($photo->getTheme())
                ->setUser($this->getUser())
                ->setDate(new \DateTime())
            ;;
            $this->em->persist($existingVote);
        }

        $this->em->flush();

        $voteRepo = $this->em->getRepository(Vote::class);

        return $this->json([
            'success' => true,
            'message' => 'Merci de votre participation',
            'newPhotoId' => $photo->getId(),
            'newVoteCount' => $voteRepo->count(['photo' => $photo]),
            'oldPhotoId' => $oldPhotoId,
            'oldVoteCount' => $oldPhotoId ? $voteRepo->count(['photo' => $oldPhotoId]) : null,
        ]);
    }

    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response {

        return $this->render('main/cgu.html.twig');
    }

    #[Route('/rgpd', name: 'app_rgpd')]
    public function rgpd(): Response {

        return $this->render('main/rgpd.html.twig');
    }
}
