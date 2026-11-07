<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Traite le retour de Google / Facebook (route app_oauth_check) et connecte le membre.
 */
final class SocialAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private SocialUserProvisioner $provisioner,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_oauth_check' === $request->attributes->get('_route')
            && in_array($request->attributes->get('provider'), SocialUserProvisioner::PROVIDERS, true);
    }

    public function authenticate(Request $request): Passport
    {
        $provider = (string) $request->attributes->get('provider');

        // Refus de l'utilisateur sur l'écran Google / Facebook
        if ($request->query->has('error')) {
            throw new CustomUserMessageAuthenticationException('Connexion annulée.');
        }

        $client = $this->clientRegistry->getClient($provider);
        // Vérifie le paramètre « state » (anti-CSRF) puis échange le code contre un jeton
        $accessToken = $this->fetchAccessToken($client);
        $owner = $client->fetchUserFromToken($accessToken);
        $providerId = (string) $owner->getId();

        return new SelfValidatingPassport(
            new UserBadge($provider . ':' . $providerId, fn () => $this->provisioner->provision(
                $provider,
                $providerId,
                $this->email($owner),
                $this->displayName($owner),
            )),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($target ?: $this->urlGenerator->generate('app_main'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Affichée par la page de connexion, comme une erreur de mot de passe
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        // Sinon « google:123… » serait pré-rempli dans le champ email de la page de connexion
        $request->getSession()->remove(SecurityRequestAttributes::LAST_USERNAME);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function email(ResourceOwnerInterface $owner): ?string
    {
        return match (true) {
            $owner instanceof GoogleUser => $owner->getEmail(),
            $owner instanceof FacebookUser => $owner->getEmail(),
            default => null,
        };
    }

    private function displayName(ResourceOwnerInterface $owner): ?string
    {
        return match (true) {
            $owner instanceof GoogleUser => $owner->getFirstName() ?: $owner->getName(),
            $owner instanceof FacebookUser => $owner->getFirstName() ?: $owner->getName(),
            default => null,
        };
    }
}
