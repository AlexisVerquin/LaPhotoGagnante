<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Retrouve ou crée le membre qui arrive par Google ou Facebook.
 * On le cherche par son identifiant chez le fournisseur, sinon par son email, que le fournisseur a déjà vérifié.
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
     *                                                  ou si le compte est suspendu
     */
    public function provision(string $provider, string $providerId, ?string $email, ?string $displayName): User
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException(sprintf('Fournisseur inconnu : %s', $provider));
        }

        $field = $provider . 'Id';

        // Déjà venu par ce fournisseur
        $user = $this->userRepository->findOneBy([$field => $providerId]);
        if ($user) {
            $this->assertActive($user);

            return $user;
        }

        $email = null !== $email ? mb_strtolower(trim($email)) : '';
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAuthenticationException(sprintf('Votre compte %s ne nous a pas transmis d\'adresse email. Autorisez le partage de l\'email, ou inscrivez-vous avec le formulaire.', ucfirst($provider)));
        }

        // Un compte existe avec cet email : c'est la même personne, on relie les deux.
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $this->assertActive($user);
        } else {
            // Nouveau venu
            $user = new User()
                ->setEmail($email)
                ->setName($this->uniqueName($displayName, $email))
                ->setRoles([])
                ->setIsActive(true);
            // Un mot de passe que personne ne connaît : il pourra s'en choisir un avec « Mot de passe oublié ».
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
        }

        // Google ou Facebook a déjà vérifié l'email.
        $user->setIsVerified(true);
        'google' === $provider ? $user->setGoogleId($providerId) : $user->setFacebookId($providerId);

        $this->entityManager->flush();

        return $user;
    }

    private function assertActive(User $user): void
    {
        if (false === $user->isActive()) {
            throw new CustomUserMessageAuthenticationException(UserChecker::SUSPENDED_MESSAGE);
        }
    }

    /**
     * Un pseudo libre, tiré du prénom ou à défaut de l'email (« Alice 2 » si « Alice » est déjà pris).
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
