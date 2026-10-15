<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmail(TemplatedEmail $email, array $discution): void
    {
        $context = $email->getContext();
        $context['discution'] = $discution;
        $email->context($context);

        $this->mailer->send($email);
    }
}
