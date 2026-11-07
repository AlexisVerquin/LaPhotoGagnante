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

final class MainController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'app_main')]
    public function index(PhotoService $photoService): Response
    {
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

        $user = $this->getUser();

        if ($user instanceof User && !$user->isVerified()) {
            $message = $this->translator->trans('alert.unverified_user');
            $this->addFlash('info', $message);
        }

        $today = new \DateTime();
        $lastVote = $user instanceof User ? $user->getLastVoteDate() : null;

        return $this->render('main/index.html.twig', [
            'actualTheme' => $actualTheme,
            'lastTheme' => $lastTheme,
            'hasPhoto' => $hasPhoto,
            'activeSurvey' => $activeSurvey,
            'totalVoteSurvey' => $totalVote,
            'alreadyVoted' => null !== $lastVote && $lastVote->format('Y-m-d') === $today->format('Y-m-d'),
            'lastWinner' => $lastWinner,
            'photoOfTheDay' => $photoOfTheDay,
            'nbPhoto' => $nbPhoto,
            'nbUsers' => $nbUsers,
            'nbVotes' => $nbVotes,
            'nbThemes' => $nbThemes,
            'allThemes' => $themeRepository->findAll(),
        ]);
    }

    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(): Response
    {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();

        if (!$actualTheme) {
            $this->addFlash('info', 'Aucun concours n\'est en cours pour le moment.');

            return $this->redirectToRoute('app_main');
        }

        $user = $this->getUser();
        $voteUser = $user instanceof User
            ? $this->em->getRepository(Vote::class)->findOneBy(['user' => $user, 'theme' => $actualTheme])
            : null;

        return $this->render('main/gallery.html.twig', [
            'actualTheme' => $actualTheme,
            'voteUser' => $voteUser,
            'enableVote' => $this->isVoteOpen($actualTheme),
        ]);
    }

    #[Route('/voteSurvey', name: 'app_main_vote', methods: ['POST'])]
    public function voteSurvey(Request $request): JsonResponse
    {
        $submittedToken = $request->headers->get('X-CSRF-Token');

        if (!$this->isCsrfTokenValid('survey-vote', $submittedToken)) {
            return $this->json(['error' => 'Token CSRF invalide'], Response::HTTP_UNAUTHORIZED); // 401
        }

        $data = $request->request->get('id');

        if (null === $data) {
            return $this->json(['error' => 'Une erreur est survenue pendant le vote, merci de réessayer'], Response::HTTP_BAD_REQUEST); // 400
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté pour pouvoir voter'], Response::HTTP_UNAUTHORIZED); // 401
        }

        if (!$user->isVerified()) {
            return $this->json(['error' => 'Vous devez confirmer votre adresse email pour pouvoir voter'], Response::HTTP_FORBIDDEN); // 403
        }

        $today = new \DateTime();
        $lastVote = $user->getLastVoteDate();

        if (null !== $lastVote && $lastVote->format('Y-m-d') === $today->format('Y-m-d')) {
            return $this->json(['error' => "Vous avez déjà voté aujourd'hui, revenez demain"], Response::HTTP_CONFLICT); // 409
        }

        $surveyChoice = $this->em->getRepository(SurveyChoice::class)->find($data);

        if (!$surveyChoice) {
            return $this->json(['error' => "Ce choix de vote n'existe pas"], Response::HTTP_NOT_FOUND); // 404
        }

        // On ne peut voter que pour un choix du sondage actuellement ouvert
        $activeSurvey = $this->em->getRepository(Survey::class)->findActiveSurvey();
        if (!$activeSurvey || $surveyChoice->getSurvey()?->getId() !== $activeSurvey->getId()) {
            return $this->json(['error' => "Ce sondage n'est pas ouvert aux votes"], Response::HTTP_FORBIDDEN); // 403
        }

        $user->setLastVoteDate($today);
        $this->em->flush();

        // Incrément atomique en base : évite de perdre des votes si deux requêtes arrivent en même temps
        $this->em->createQueryBuilder()
            ->update(SurveyChoice::class, 'sc')
            ->set('sc.numberVoter', 'sc.numberVoter + 1')
            ->where('sc.id = :id')
            ->setParameter('id', $surveyChoice->getId())
            ->getQuery()
            ->execute();
        $this->em->refresh($surveyChoice);

        $total = array_sum(array_map(
            fn ($choice) => $choice->getNumberVoter(),
            $surveyChoice->getSurvey()->getSurveyChoices()->toArray(),
        ));

        return $this->json([
            'message' => "Merci d'avoir voté, n'hésitez pas à revenir demain",
            'vote' => $surveyChoice->getNumberVoter(),
            'alreadyVoted' => true,
            'total' => $total,
        ]);
    }

    #[Route('/locale/{locale}', name: 'app_change_locale', requirements: ['locale' => 'en|fr'])]
    public function changeLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);

        return $this->redirectToRoute('app_main');
    }

    #[Route('/vote', name: 'app_vote', methods: ['POST'])]
    public function vote(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('vote-photo-theme', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            $cartUrl = $this->generateUrl('app_login');

            return $this->json(
                [
                    'success' => false,
                    'message' => sprintf(
                        'Vous devez etre connecté pour pouvoir voter. <a href="%s" class="alert-link">connectez-vous</a>',
                        $cartUrl,
                    ),
                ], 200,
            );
        }

        if (!$user->isVerified()) {
            return $this->json(['success' => false, 'message' => 'Vous devez confirmer votre adresse email pour pouvoir voter'], Response::HTTP_FORBIDDEN);
        }

        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        if (!$actualTheme) {
            return $this->json(['success' => false, 'message' => 'Aucun concours en cours'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isVoteOpen($actualTheme)) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Les votes seront activés le ' . $this->getVoteOpeningDate($actualTheme)->format('d-m-Y'),
                ], 200,
            );
        }

        $photo = $this->em->getRepository(Photo::class)->find($request->request->getInt('photoId'));

        // La photo doit exister, être validée par un admin et appartenir au concours en cours
        if (!$photo || !$photo->isVisible() || $photo->getTheme()?->getId() !== $actualTheme->getId()) {
            return $this->json(['success' => false, 'message' => 'Photo introuvable'], 404);
        }

        $existingVote = $this->em->getRepository(Vote::class)->findOneBy([
            'user' => $user,
            'theme' => $actualTheme,
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
            $existingVote = new Vote()
                ->setPhoto($photo)
                ->setTheme($photo->getTheme())
                ->setUser($user)
                ->setDate(new \DateTime())
            ;
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

    private function getVoteOpeningDate(Theme $theme): \DateTime
    {
        $date = clone $theme->getDateEnd();

        return $date->modify('-6 day');
    }

    private function isVoteOpen(Theme $theme): bool
    {
        return $this->getVoteOpeningDate($theme)->format('Y-m-d') <= new \DateTime()->format('Y-m-d');
    }

    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        return $this->render('main/cgu.html.twig');
    }

    #[Route('/rgpd', name: 'app_rgpd')]
    public function rgpd(): Response
    {
        return $this->render('main/rgpd.html.twig');
    }
}
