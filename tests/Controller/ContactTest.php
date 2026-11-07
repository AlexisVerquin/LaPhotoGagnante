<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Tests\FunctionalTestCase;

final class ContactTest extends FunctionalTestCase
{
    private function send(string $website = ''): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->filter('form[name="contact"]')->form();

        $this->client->submit($form, [
            'contact[email]' => 'visiteur@example.com',
            'contact[message]' => 'Bonjour, une question sur le concours du mois.',
            'contact[website]' => $website,
        ]);
    }

    public function testMessageIsSaved(): void
    {
        $this->send();

        self::assertResponseRedirects('/contact');
        self::assertSame(1, $this->em->getRepository(Contact::class)->count());
    }

    public function testHoneypotSilentlyDropsBots(): void
    {
        $this->send(website: 'https://spam.example');

        // Le robot croit que c'est passé, mais on n'a rien gardé.
        self::assertResponseRedirects('/contact');
        self::assertSame(0, $this->em->getRepository(Contact::class)->count());
    }

    public function testNoMoreThanThreeMessagesPerHour(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->send();
            self::assertResponseRedirects('/contact');
        }

        $this->send();

        self::assertResponseStatusCodeSame(429);
        self::assertSame(3, $this->em->getRepository(Contact::class)->count());
    }

    public function testInvalidEmailIsRefused(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $this->client->submit($crawler->filter('form[name="contact"]')->form(), [
            'contact[email]' => 'pas-un-email',
            'contact[message]' => 'Bonjour, une question sur le concours du mois.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Contact::class)->count());
    }
}
