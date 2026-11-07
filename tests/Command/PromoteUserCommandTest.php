<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Tests\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteUserCommandTest extends FunctionalTestCase
{
    /**
     * @param array<string, mixed> $input
     */
    private function promote(array $input): int
    {
        $application = new Application(static::$kernel ?? static::bootKernel());

        return new CommandTester($application->find('app:user:promote'))->execute($input);
    }

    public function testMemberBecomesAdminThenIsRevoked(): void
    {
        $user = $this->createUser();

        self::assertSame(Command::SUCCESS, $this->promote(['email' => 'membre@example.com']));
        self::assertContains('ROLE_ADMIN', $this->reload(User::class, $user->getId())?->getRoles() ?? []);

        self::assertSame(Command::SUCCESS, $this->promote(['email' => 'membre@example.com', '--revoke' => true]));
        self::assertNotContains('ROLE_ADMIN', $this->reload(User::class, $user->getId())?->getRoles() ?? []);
    }

    public function testUnknownEmailFails(): void
    {
        self::assertSame(Command::FAILURE, $this->promote(['email' => 'inconnu@example.com']));
    }
}
