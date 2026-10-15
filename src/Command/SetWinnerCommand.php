<?php

namespace App\Command;

use App\Entity\Theme;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:set-winner',
    description: 'Add a short description for your command',
)]
class SetWinnerCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager, private MailService $mailService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $discution = [];

        $acthalTheme = $this->entityManager->getRepository(Theme::class)->findActualTheme();
        $photos = $acthalTheme->getPhotos();

        $discution['id'] = $acthalTheme->getId();

        try {
            $winner = null;
            foreach ($photos as $photo) {
                if (null === $winner) {
                    $winner = $photo;
                } elseif (count($photo->getVotes()) > count($winner->getVotes())) {
                    $winner = $photo;
                } elseif (count($photo->getVotes()) === count($winner->getVotes())) {
                    $winner = ($winner->getCreatedAt() < $photo->getCreatedAt()) ? $winner : $photo;
                }
            }

            $acthalTheme->setWinner($winner->getUser());
            $discution['message'] = $winner->getUser()->getName() . ' id : ' . $winner->getUser()->getId();
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            $discution['message'] = $exception->getMessage();

            $this->mailService->sendEmail(
                new TemplatedEmail()
                    ->from(new Address('admin@${PROJECT_NAME}.fr', '${PROJECT_NAME}'))
                    ->to((string) 'alexis.verquin@gmail.com')
                    ->subject('erreur lors de la selection du gagnant')
                    ->htmlTemplate('contact/toAdmin.html.twig'),
                $discution,
            );

            return Command::FAILURE;
        }

        $this->mailService->sendEmail(
            new TemplatedEmail()
                ->from(new Address('admin@${PROJECT_NAME}.fr', '${PROJECT_NAME}'))
                ->to((string) 'alexis.verquin@gmail.com')
                ->subject('succés de la selection du gagnant')
                ->htmlTemplate('contact/toAdmin.html.twig'),
            $discution,
        );

        $io->success('gagnant selectionné avec success');

        return Command::SUCCESS;
    }
}
