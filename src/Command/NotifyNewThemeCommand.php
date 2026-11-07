<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ThemeNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie l'email « nouveau concours » aux membres abonnés, une seule fois par concours.
 */
#[AsCommand(
    name: 'app:notify-new-theme',
    description: 'Prévient les membres abonnés du lancement du concours en cours (une fois par concours)',
)]
final class NotifyNewThemeCommand extends Command
{
    public function __construct(private ThemeNotifier $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sent = $this->notifier->notifyCurrentTheme();

        null === $sent
            ? $io->note('Aucun nouveau concours à annoncer.')
            : $io->success(sprintf('%d membre(s) prévenu(s) du nouveau concours.', $sent));

        return Command::SUCCESS;
    }
}
