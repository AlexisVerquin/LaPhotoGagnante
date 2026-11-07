<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Theme;
use App\Entity\User;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prévient les membres abonnés qu'un nouveau concours a commencé, une seule fois par concours.
 */
final class ThemeNotifier
{
    public function __construct(
        private ThemeRepository $themeRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
    ) {
    }

    /**
     * @return int|null nombre d'emails envoyés, ou null s'il n'y avait rien à annoncer
     */
    public function notifyCurrentTheme(): ?int
    {
        $theme = $this->themeRepository->findActualTheme();
        if (!$theme || null !== $theme->getNotifiedAt()) {
            return null;
        }

        $recipients = $this->em->getRepository(User::class)->findBy([
            'notifyNewTheme' => true,
            'isVerified' => true,
            'isActive' => true,
        ]);

        foreach ($recipients as $user) {
            $this->mailer->send($this->buildEmail($theme, $user));
        }

        $theme->setNotifiedAt(new \DateTimeImmutable());
        $this->em->flush();

        return count($recipients);
    }

    /**
     * Lien signé : il suffit de cliquer pour se désinscrire, sans se connecter, et personne ne peut le deviner.
     */
    public function unsubscribeUrl(User $user): string
    {
        return $this->uriSigner->sign($this->urlGenerator->generate(
            'app_unsubscribe',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    private function buildEmail(Theme $theme, User $user): TemplatedEmail
    {
        $unsubscribeUrl = $this->unsubscribeUrl($user);
        $themeName = (string) $theme->getThemeChoice()?->getName();

        $email = new TemplatedEmail()
            ->to((string) $user->getEmail())
            ->subject('Nouveau concours : ' . $themeName)
            ->htmlTemplate('emails/new_theme.html.twig')
            ->context([
                'themeName' => $themeName,
                'themeEnd' => $theme->getDateEnd(),
                'userName' => $user->getName(),
                'siteUrl' => $this->urlGenerator->generate('app_main', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);

        // Le bouton « Se désabonner » de Gmail ou Outlook appelle directement ce lien (RFC 8058)
        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
            ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        return $email;
    }
}
