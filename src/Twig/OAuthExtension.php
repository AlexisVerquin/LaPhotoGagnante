<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * Indique quels fournisseurs de connexion sont configurés (identifiant renseigné).
 */
final class OAuthExtension
{
    public function __construct(
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_ID)%')] private string $googleClientId,
        #[Autowire('%env(OAUTH_FACEBOOK_CLIENT_ID)%')] private string $facebookClientId,
    ) {
    }

    #[AsTwigFunction('oauth_enabled')]
    public function isEnabled(string $provider): bool
    {
        return match ($provider) {
            'google' => '' !== trim($this->googleClientId),
            'facebook' => '' !== trim($this->facebookClientId),
            default => false,
        };
    }
}
