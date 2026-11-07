<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Donne (ou retire) le rôle administrateur à un membre déjà inscrit.
 * Usage : php bin/console app:user:promote alexis@example.fr [--revoke].
 */
#[AsCommand(
    name: 'app:user:promote',
    description: 'Donne le rôle administrateur à un membre inscrit (--revoke pour le retirer)',
)]
final class PromoteUserCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email du membre')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Retire le rôle administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('Aucun membre avec l\'email %s. Inscrivez-vous d\'abord sur le site.', $email));

            return Command::FAILURE;
        }

        $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']));
        if (!$input->getOption('revoke')) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s %s administrateur.',
            $email,
            $input->getOption('revoke') ? 'n\'est plus' : 'est maintenant',
        ));

        return Command::SUCCESS;
    }
}
