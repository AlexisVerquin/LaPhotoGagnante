<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Tests\FunctionalTestCase;

final class LoginThrottlingTest extends FunctionalTestCase
{
    private function login(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form button[type="submit"]')->form();

        $this->client->submit($form, ['_username' => $email, '_password' => $password]);
    }

    public function testValidCredentialsLogTheUserIn(): void
    {
        $this->createUser('membre@example.com');

        $this->login('membre@example.com', self::PASSWORD);
        $this->client->followRedirect();

        self::assertNotNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function testAccountIsLockedAfterThreeFailedAttempts(): void
    {
        $this->createUser('membre@example.com');

        for ($i = 0; $i < 3; ++$i) {
            $this->login('membre@example.com', 'mauvais-mot-de-passe');
            $this->client->followRedirect();
            self::assertSelectorExists('#login-error');
        }

        // Même avec le bon mot de passe, la 4e tentative est refusée.
        $this->login('membre@example.com', self::PASSWORD);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorExists('#login-error');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }
}
