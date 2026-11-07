<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prévient tous les membres d'un changement important des CGU ou de la politique de confidentialité,
 * comme promis dans ces textes. À lancer après avoir mis à jour legal.updated_at.
 */
#[AsCommand(
    name: 'app:legal:notify-update',
    description: 'Envoie à tous les membres un email résumant les changements des CGU / de la politique de confidentialité',
)]
final class NotifyLegalUpdateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        /** @var array<string, mixed> */
        #[Autowire('%app.legal%')]
        private array $legal,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('summary', InputArgument::REQUIRED, 'Ce qui change, en quelques lignes compréhensibles par tous')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le nombre de destinataires sans rien envoyer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Tous les membres joignables, même désabonnés des annonces de concours : c'est une information sur le service
        $members = $this->em->getRepository(User::class)->findBy(['isVerified' => true, 'isActive' => true]);

        if ($input->getOption('dry-run')) {
            $io->note(sprintf('%d membre(s) seraient prévenus.', count($members)));

            return Command::SUCCESS;
        }

        foreach ($members as $member) {
            $this->mailer->send(new TemplatedEmail()
                ->to((string) $member->getEmail())
                ->subject('Nos conditions d\'utilisation évoluent')
                ->htmlTemplate('emails/legal_update.html.twig')
                ->context([
                    'userName' => $member->getName(),
                    'summary' => (string) $input->getArgument('summary'),
                    'updatedAt' => $this->legal['updated_at'] ?? 'now',
                    'cguUrl' => $this->urlGenerator->generate('app_cgu', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'privacyUrl' => $this->urlGenerator->generate('app_rgpd', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ]));
        }

        $io->success(sprintf('%d membre(s) prévenu(s).', count($members)));

        return Command::SUCCESS;
    }
}
