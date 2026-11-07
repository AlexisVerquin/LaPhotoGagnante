<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Photo;
use App\Service\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À lancer une fois au déploiement : déplace hors du dossier web les photos non validées
 * envoyées avant la mise en place de la zone privée. Sans risque à relancer.
 */
#[AsCommand(
    name: 'app:photos:secure-pending',
    description: 'Déplace les photos en attente de validation hors du dossier public',
)]
final class SecurePendingPhotosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhotoStorage $photoStorage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les fichiers sans les déplacer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $moved = 0;

        foreach ($this->entityManager->getRepository(Photo::class)->findBy(['is_visible' => false]) as $photo) {
            if (!is_file($this->photoStorage->publicPath($photo))) {
                continue;
            }

            $io->writeln(sprintf('Photo #%d : %s', $photo->getId(), $photo->getName()));
            if (!$dryRun) {
                $this->photoStorage->unpublish($photo);
            }
            ++$moved;
        }

        $io->success(sprintf('%d photo(s) %s.', $moved, $dryRun ? 'à déplacer' : 'déplacée(s) hors du dossier public'));

        return Command::SUCCESS;
    }
}
