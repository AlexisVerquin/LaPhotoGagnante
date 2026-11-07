<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Retrouve ou crée le membre correspondant à un compte Google / Facebook.
 *
 * Ordre de recherche :
 *  1. compte déjà lié à cet identifiant Google / Facebook ;
 *  2. compte existant avec la même adresse email (les deux fournisseurs ne transmettent
 *     que des emails vérifiés) : il est lié automatiquement ;
 *  3. sinon création d'un compte, email considéré comme vérifié, mot de passe inutilisable
 *     (le membre peut en définir un via « Mot de passe oublié »).
 */
final class SocialUserProvisioner
{
    public const PROVIDERS = ['google', 'facebook'];

    private const NAME_MAX_LENGTH = 50;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @throws CustomUserMessageAuthenticationException si le fournisseur ne transmet pas d'email
     *                                                  ou si le compte a été supprimé
     */
    public function provision(string $provider, string $providerId, ?string $email, ?string $displayName): User
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException(sprintf('Fournisseur inconnu : %s', $provider));
        }

        $field = $provider . 'Id';

        // 1. Déjà lié
        $user = $this->userRepository->findOneBy([$field => $providerId]);
        if ($user) {
            $this->assertActive($user);

            return $user;
        }

        $email = null !== $email ? mb_strtolower(trim($email)) : '';
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAuthenticationException(sprintf('Votre compte %s ne nous a pas transmis d\'adresse email. Autorisez le partage de l\'email, ou inscrivez-vous avec le formulaire.', ucfirst($provider)));
        }

        // 2. Même email : on lie le compte existant
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $this->assertActive($user);
        } else {
            // 3. Nouveau membre
            $user = new User()
                ->setEmail($email)
                ->setName($this->uniqueName($displayName, $email))
                ->setRoles([])
                ->setIsActive(true);
            // Mot de passe aléatoire jamais communiqué : connexion uniquement via le fournisseur
            // (ou après « Mot de passe oublié »)
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
        }

        // L'email est vérifié par le fournisseur
        $user->setIsVerified(true);
        'google' === $provider ? $user->setGoogleId($providerId) : $user->setFacebookId($providerId);

        $this->entityManager->flush();

        return $user;
    }

    private function assertActive(User $user): void
    {
        if (false === $user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Ce compte a été supprimé.');
        }
    }

    /**
     * Nom d'utilisateur unique (contrainte UniqueEntity sur User::$name) tiré du profil, sinon de l'email.
     */
    private function uniqueName(?string $displayName, string $email): string
    {
        $base = trim((string) $displayName);
        if ('' === $base) {
            $base = (string) $this->slugger->slug(strstr($email, '@', true) ?: 'membre');
        }
        $base = mb_substr($base, 0, self::NAME_MAX_LENGTH - 5);

        $name = $base;
        for ($i = 2; $this->userRepository->findOneBy(['name' => $name]); ++$i) {
            $name = sprintf('%s %d', $base, $i);
        }

        return $name;
    }
}
