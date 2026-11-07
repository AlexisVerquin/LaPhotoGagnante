<?php

declare(strict_types=1);

namespace App\Tests\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\SocialUserProvisioner;
use App\Tests\FunctionalTestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class SocialUserProvisionerTest extends FunctionalTestCase
{
    private function provisioner(): SocialUserProvisioner
    {
        return static::getContainer()->get(SocialUserProvisioner::class);
    }

    public function testNewMemberIsCreatedVerifiedAndLinked(): void
    {
        $user = $this->provisioner()->provision('google', 'g-123', 'Alice@Example.com', 'Alice');

        self::assertNotNull($user->getId());
        self::assertSame('alice@example.com', $user->getEmail());
        self::assertSame('Alice', $user->getName());
        self::assertSame('g-123', $user->getGoogleId());
        self::assertTrue($user->isVerified(), 'Email vérifié par Google');
        self::assertNotEmpty($user->getPassword(), 'Mot de passe aléatoire (colonne NOT NULL)');
    }

    public function testExistingAccountWithSameEmailIsLinked(): void
    {
        $existing = $this->createUser('membre@example.com', verified: false);

        $user = $this->provisioner()->provision('facebook', 'fb-9', 'membre@example.com', 'Autre nom');

        self::assertSame($existing->getId(), $user->getId());
        self::assertSame('fb-9', $user->getFacebookId());
        self::assertSame('Membre', $user->getName(), 'Le nom existant est conservé');
        self::assertTrue($user->isVerified());
        self::assertSame(1, $this->em->getRepository(User::class)->count());
    }

    public function testLinkedAccountIsFoundEvenIfTheProviderEmailChanged(): void
    {
        $first = $this->provisioner()->provision('google', 'g-123', 'ancien@example.com', 'Alice');

        $again = $this->provisioner()->provision('google', 'g-123', 'nouveau@example.com', 'Alice');

        self::assertSame($first->getId(), $again->getId());
        self::assertSame(1, $this->em->getRepository(User::class)->count());
    }

    public function testUsernameIsMadeUnique(): void
    {
        $this->createUser('alice1@example.com', name: 'Alice');

        $user = $this->provisioner()->provision('google', 'g-2', 'alice2@example.com', 'Alice');

        self::assertSame('Alice 2', $user->getName());
    }

    public function testUsernameFallsBackToTheEmail(): void
    {
        $user = $this->provisioner()->provision('facebook', 'fb-1', 'jean.dupont@example.com', null);

        self::assertSame('jean-dupont', $user->getName());
    }

    public function testMissingEmailIsRefused(): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);

        $this->provisioner()->provision('facebook', 'fb-sans-email', null, 'Bob');
    }

    public function testSuspendedAccountCannotLogIn(): void
    {
        $user = $this->provisioner()->provision('google', 'g-123', 'alice@example.com', 'Alice');
        $user->setIsActive(false);
        $this->em->flush();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->provisioner()->provision('google', 'g-123', 'alice@example.com', 'Alice');
    }
}
