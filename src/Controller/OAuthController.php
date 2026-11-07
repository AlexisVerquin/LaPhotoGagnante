<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\OAuth\SocialAuthenticator;
use App\Twig\OAuthExtension;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Continuer avec Google / Facebook » : sert à la fois à l'inscription et à la connexion.
 */
final class OAuthController extends AbstractController
{
    /** Données demandées au fournisseur : uniquement l'email et le nom (minimisation RGPD) */
    private const SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        'facebook' => ['email', 'public_profile'],
    ];

    #[Route('/connect/{provider}', name: 'app_oauth_start', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function start(string $provider, ClientRegistry $clientRegistry, OAuthExtension $oauth): Response
    {
        if (!$oauth->isEnabled($provider)) {
            throw $this->createNotFoundException();
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_main');
        }

        return $clientRegistry->getClient($provider)->redirect(self::SCOPES[$provider], []);
    }

    /**
     * Retour du fournisseur : intercepté par App\Security\OAuth\SocialAuthenticator.
     */
    #[Route('/connect/{provider}/check', name: 'app_oauth_check', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function check(): Response
    {
        throw new \LogicException('Cette route est gérée par ' . SocialAuthenticator::class);
    }
}
