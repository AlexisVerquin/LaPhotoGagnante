<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DataRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime ce qu'on a promis de ne pas garder : photos des concours terminés et messages clôturés.
 */
#[AsCommand(
    name: 'app:data:purge',
    description: 'Applique les durées de conservation de la politique de confidentialité',
)]
final class PurgeDataCommand extends Command
{
    public function __construct(private DataRetention $retention)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte ce qui serait supprimé, sans rien supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $counts = $this->retention->purge(dryRun: $dryRun);

        $io->success(sprintf(
            '%s : %d photo(s), %d message(s) de contact, %d ancien(s) compte(s) anonymisé(s).',
            $dryRun ? 'À supprimer' : 'Supprimé',
            $counts['photos'],
            $counts['messages'],
            $counts['accounts'],
        ));

        return Command::SUCCESS;
    }
}
