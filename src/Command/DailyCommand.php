<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La tâche du cron quotidien. L'ordre compte : la purge garde la photo gagnante,
 * donc le gagnant doit être désigné avant.
 */
#[AsCommand(
    name: 'app:daily',
    description: 'Tâches quotidiennes : gagnant, annonce du nouveau concours, purge des données',
)]
final class DailyCommand extends Command
{
    private const TASKS = ['app:set-winner', 'app:notify-new-theme', 'app:data:purge'];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $status = Command::SUCCESS;

        foreach (self::TASKS as $name) {
            $io->section($name);
            // Une tâche en échec n'empêche pas les suivantes (et l'admin est prévenu par app:set-winner)
            if (Command::SUCCESS !== $this->getApplication()?->find($name)->run(new ArrayInput([]), $output)) {
                $status = Command::FAILURE;
            }
        }

        return $status;
    }
}
