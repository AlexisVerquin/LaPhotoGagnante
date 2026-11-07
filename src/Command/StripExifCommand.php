<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageSanitizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Supprime les métadonnées EXIF des images déjà présentes dans public/uploads
 * (photos envoyées avant la mise en place du nettoyage automatique).
 * Sans risque à relancer : une image déjà nettoyée est simplement ré-encodée.
 */
#[AsCommand(
    name: 'app:photos:strip-exif',
    description: 'Supprime les métadonnées EXIF des images déjà envoyées (photos et avatars)',
)]
final class StripExifCommand extends Command
{
    public function __construct(
        private ImageSanitizer $imageSanitizer,
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadsDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les fichiers sans les modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_dir($this->uploadsDirectory)) {
            $io->warning(sprintf('Dossier introuvable : %s', $this->uploadsDirectory));

            return Command::SUCCESS;
        }

        $finder = new Finder()
            ->files()
            ->in($this->uploadsDirectory)
            ->name('/\.(jpe?g|png|webp)$/i');

        $done = 0;
        $errors = 0;

        foreach ($finder as $file) {
            if ($dryRun) {
                $io->writeln($file->getRelativePathname());
                ++$done;
                continue;
            }

            try {
                $this->imageSanitizer->sanitize($file->getPathname());
                ++$done;
            } catch (\RuntimeException $e) {
                $io->error(sprintf('%s : %s', $file->getRelativePathname(), $e->getMessage()));
                ++$errors;
            }
        }

        $io->success(sprintf('%d image(s) %s, %d erreur(s).', $done, $dryRun ? 'trouvée(s)' : 'nettoyée(s)', $errors));

        return 0 === $errors ? Command::SUCCESS : Command::FAILURE;
    }
}
