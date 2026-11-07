<?php

declare(strict_types=1);

namespace App\Tests\Security\OAuth;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Remplace le client Google / Facebook dans les tests : renvoie directement le profil voulu,
 * sans appel réseau ni vérification du « state ».
 */
final class FakeOAuthClient implements OAuth2ClientInterface
{
    public function __construct(private ResourceOwnerInterface $owner)
    {
    }

    public function setAsStateless(): void
    {
    }

    /**
     * @param list<string>         $scopes
     * @param array<string, mixed> $options
     */
    public function redirect(array $scopes, array $options): RedirectResponse
    {
        return new RedirectResponse('https://fournisseur.test/authorize');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getAccessToken(array $options = []): AccessToken
    {
        return new AccessToken(['access_token' => 'faux-jeton']);
    }

    public function fetchUserFromToken(AccessToken $accessToken): ResourceOwnerInterface
    {
        return $this->owner;
    }

    public function fetchUser(): ResourceOwnerInterface
    {
        return $this->owner;
    }

    public function getOAuth2Provider(): AbstractProvider
    {
        throw new \LogicException('Non utilisé dans les tests');
    }
}
