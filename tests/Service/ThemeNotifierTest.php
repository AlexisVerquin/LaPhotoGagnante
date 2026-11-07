<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Theme;
use App\Entity\User;
use App\Service\ThemeNotifier;
use App\Tests\FunctionalTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

final class ThemeNotifierTest extends FunctionalTestCase
{
    private function notifier(): ThemeNotifier
    {
        return static::getContainer()->get(ThemeNotifier::class);
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        return array_map(
            static fn (TemplatedEmail $email): string => $email->getTo()[0]->getAddress(),
            array_values(array_filter(static::getMailerMessages(), static fn ($m) => $m instanceof TemplatedEmail)),
        );
    }

    public function testOnlySubscribedVerifiedMembersAreNotifiedOnce(): void
    {
        $theme = $this->createTheme();
        $this->createUser('abonne@example.com', name: 'Abonné');
        $this->createUser('pas-verifie@example.com', verified: false, name: 'Pas vérifié');
        $this->createUser('desabonne@example.com', name: 'Désabonné')->setNotifyNewTheme(false);
        $this->em->flush();

        self::assertSame(1, $this->notifier()->notifyCurrentTheme());
        self::assertSame(['abonne@example.com'], $this->recipients());
        self::assertNotNull($this->reload(Theme::class, $theme->getId())?->getNotifiedAt());

        self::assertNull($this->notifier()->notifyCurrentTheme(), 'Un concours n\'est annoncé qu\'une fois');
    }

    public function testEmailContainsAOneClickUnsubscribeLink(): void
    {
        $this->createTheme(name: 'Reflets');
        $this->createUser('abonne@example.com', name: 'Abonné');

        $this->notifier()->notifyCurrentTheme();

        $email = static::getMailerMessages()[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('Nouveau concours : Reflets', $email->getSubject());
        self::assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());
        $unsubscribe = (string) $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString();
        self::assertMatchesRegularExpression('#^<https?://.+/desinscription/\d+\?.*_hash=.+>$#', $unsubscribe);
        self::assertStringContainsString(htmlspecialchars(trim($unsubscribe, '<>')), (string) $email->getHtmlBody(), 'Le même lien est dans le pied de l\'email');
        self::assertEquals([new Address('abonne@example.com')], $email->getTo());
    }

    public function testNothingToAnnounceWithoutContest(): void
    {
        $this->createUser();

        self::assertNull($this->notifier()->notifyCurrentTheme());
        self::assertSame([], $this->recipients());
        self::assertSame(1, $this->em->getRepository(User::class)->count());
    }
}
