<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion d'un compte suspendu par la modération, quel que soit le moyen (mot de passe, Google, Facebook).
 */
final class UserChecker implements UserCheckerInterface
{
    public const SUSPENDED_MESSAGE = 'Votre compte est suspendu. Pour en savoir plus, écrivez-nous via le formulaire de contact.';

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && false === $user->isActive()) {
            throw new CustomUserMessageAccountStatusException(self::SUSPENDED_MESSAGE);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
