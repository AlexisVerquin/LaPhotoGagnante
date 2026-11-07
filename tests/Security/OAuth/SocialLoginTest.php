<?php

declare(strict_types=1);

namespace App\Tests\Security\OAuth;

use App\Entity\User;
use App\Tests\FunctionalTestCase;
use App\Twig\OAuthExtension;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class SocialLoginTest extends FunctionalTestCase
{
    private function fakeProvider(string $provider, ResourceOwnerInterface $owner): void
    {
        static::getContainer()->set('knpu.oauth2.client.' . $provider, new FakeOAuthClient($owner));
    }

    public function testButtonsAreShownOnLoginAndRegisterPages(): void
    {
        foreach (['/login', '/register'] as $page) {
            $crawler = $this->client->request('GET', $page);

            self::assertCount(1, $crawler->filter('a[href="/connect/google"]'), $page);
            self::assertCount(1, $crawler->filter('a[href="/connect/facebook"]'), $page);
        }
    }

    public function testStartRedirectsToGoogleWithMinimalScopes(): void
    {
        $this->client->request('GET', '/connect/google');

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://accounts.google.com/', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('test-google-client-id', $query['client_id']);
        self::assertSame('openid email profile', $query['scope']);
        self::assertNotEmpty($query['state'], 'Protection anti-CSRF (state)');
        self::assertStringEndsWith('/connect/google/check', (string) $query['redirect_uri']);
    }

    public function testGoogleSignUpCreatesTheAccountAndLogsIn(): void
    {
        $this->fakeProvider('google', new GoogleUser([
            'sub' => 'g-42', 'email' => 'alice@example.com', 'given_name' => 'Alice', 'name' => 'Alice Martin',
        ]));

        $this->client->request('GET', '/connect/google/check?code=abc&state=xyz');

        self::assertResponseRedirects('/');
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => 'g-42']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified());

        // Connecté : l'espace membre est accessible
        $this->client->request('GET', '/user/manage');
        self::assertResponseIsSuccessful();
    }

    public function testFacebookLoginLinksTheExistingAccount(): void
    {
        $existing = $this->createUser('membre@example.com');
        $this->fakeProvider('facebook', new FacebookUser([
            'id' => 'fb-7', 'email' => 'membre@example.com', 'first_name' => 'Membre',
        ]));

        $this->client->request('GET', '/connect/facebook/check?code=abc&state=xyz');

        self::assertResponseRedirects('/');
        self::assertSame('fb-7', $this->reload(User::class, $existing->getId())?->getFacebookId());
        self::assertSame(1, $this->em->getRepository(User::class)->count());
    }

    public function testFacebookAccountWithoutEmailIsRefusedWithAMessage(): void
    {
        $this->fakeProvider('facebook', new FacebookUser(['id' => 'fb-8', 'first_name' => 'Bob']));

        $this->client->request('GET', '/connect/facebook/check?code=abc&state=xyz');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#login-error', 'adresse email');
        self::assertInputValueSame('_username', '', 'L\'identifiant technique ne doit pas être pré-rempli');
        self::assertSame(0, $this->em->getRepository(User::class)->count());
    }

    public function testCancellingOnTheProviderScreenShowsAMessage(): void
    {
        $this->client->request('GET', '/connect/google/check?error=access_denied&state=xyz');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#login-error', 'annulée');
    }

    public function testDeletedAccountIsUnlinkedFromGoogle(): void
    {
        $this->fakeProvider('google', new GoogleUser(['sub' => 'g-42', 'email' => 'alice@example.com', 'given_name' => 'Alice']));
        $this->client->request('GET', '/connect/google/check?code=abc&state=xyz');
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => 'g-42']);
        self::assertInstanceOf(User::class, $user);

        $crawler = $this->client->request('GET', '/user/manage');
        $this->client->submit($crawler->filter('form[action="/user/delete"]')->form());

        self::assertNull($this->reload(User::class, $user->getId())?->getGoogleId());
    }

    public function testUnconfiguredProviderIsHidden(): void
    {
        $extension = new OAuthExtension(googleClientId: '', facebookClientId: 'id');

        self::assertFalse($extension->isEnabled('google'));
        self::assertTrue($extension->isEnabled('facebook'));
        self::assertFalse($extension->isEnabled('twitter'));
    }
}
