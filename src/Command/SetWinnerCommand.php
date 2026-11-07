<?php

namespace App\Command;

use App\Entity\Photo;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * À lancer (cron) après la fin d'un concours : désigne le gagnant du dernier thème terminé.
 * Règles : seules les photos validées comptent ; à égalité de votes, la photo envoyée en premier gagne.
 */
#[AsCommand(
    name: 'app:set-winner',
    description: 'Désigne le gagnant du dernier concours terminé',
)]
class SetWinnerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ThemeRepository $themeRepository,
        private MailService $mailService,
        #[Autowire('%app.admin_email%')] private string $adminEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $discution = [];

        $theme = $this->themeRepository->findLastFinishedTheme();

        if (!$theme) {
            $io->warning('Aucun concours terminé.');

            return Command::SUCCESS;
        }

        $discution['id'] = $theme->getId();

        if ($theme->getWinner()) {
            $io->note(sprintf('Le concours #%d a déjà un gagnant, rien à faire.', $theme->getId()));

            return Command::SUCCESS;
        }

        try {
            $winner = $this->findWinningPhoto($theme);

            if (!$winner || !$winner->getUser()) {
                throw new \RuntimeException(sprintf('Aucune photo validée pour le concours #%d', $theme->getId()));
            }

            $theme->setWinner($winner->getUser());
            $discution['message'] = $winner->getUser()->getName() . ' id : ' . $winner->getUser()->getId();
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            $discution['message'] = $exception->getMessage();
            $this->notifyAdmin('Erreur lors de la sélection du gagnant', $discution);

            return Command::FAILURE;
        }

        $this->notifyAdmin('Succès de la sélection du gagnant', $discution);
        $io->success('Gagnant sélectionné avec succès');

        return Command::SUCCESS;
    }

    private function findWinningPhoto(Theme $theme): ?Photo
    {
        $winner = null;
        $winnerVotes = -1;

        foreach ($theme->getPhotos() as $photo) {
            if (!$photo->isVisible()) {
                continue;
            }

            $votes = $photo->getVotes()->count();
            if ($votes > $winnerVotes
                || ($votes === $winnerVotes && $photo->getCreatedAt() < $winner?->getCreatedAt())
            ) {
                $winner = $photo;
                $winnerVotes = $votes;
            }
        }

        return $winner;
    }

    /**
     * @param array<string, mixed> $discution
     */
    private function notifyAdmin(string $subject, array $discution): void
    {
        $this->mailService->sendEmail(
            new TemplatedEmail()
                ->to($this->adminEmail)
                ->subject($subject)
                ->htmlTemplate('contact/toAdmin.html.twig'),
            $discution,
        );
    }
}
