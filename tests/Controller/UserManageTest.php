<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FunctionalTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManageTest extends FunctionalTestCase
{
    private const NEW_PASSWORD = 'N0uveau-Mot-De-Passe-Tres-Solide!';

    /**
     * @param array<string, string> $values
     */
    private function submitProfile(array $values): void
    {
        $crawler = $this->client->request('GET', '/user/manage');
        $form = $crawler->filter('form[name="user_info"]')->form();
        $this->client->submit($form, $values);
    }

    private function passwordIsValid(User $user, string $password): bool
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        return $hasher->isPasswordValid($user, $password);
    }

    public function testPasswordCanBeChanged(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->submitProfile([
            'user_info[password]' => self::PASSWORD,
            'user_info[new_password]' => self::NEW_PASSWORD,
            'user_info[password_confirm]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseRedirects('/user/manage');
        $reloaded = $this->reload(User::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($this->passwordIsValid($reloaded, self::NEW_PASSWORD));
    }

    public function testSuccessMessageUsesAStyledAlertType(): void
    {
        // Avant, le type « alert-success » donnait une alerte sans fond.
        $this->client->loginUser($this->createUser());

        $this->submitProfile(['user_info[city]' => 'Lille']);
        $crawler = $this->client->followRedirect();

        $flashes = json_decode((string) $crawler->filter('body')->attr('data-alert-flashes-value'), true);
        self::assertIsArray($flashes);
        self::assertNotEmpty($flashes);
        foreach ($flashes as $flash) {
            self::assertContains($flash['type'], ['success', 'danger', 'warning', 'info']);
        }
    }

    public function testWrongCurrentPasswordIsRefused(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->submitProfile([
            'user_info[password]' => 'pas-le-bon',
            'user_info[new_password]' => self::NEW_PASSWORD,
            'user_info[password_confirm]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(422);
        $reloaded = $this->reload(User::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($this->passwordIsValid($reloaded, self::PASSWORD));
    }

    public function testConfirmationMustMatch(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->submitProfile([
            'user_info[password]' => self::PASSWORD,
            'user_info[new_password]' => self::NEW_PASSWORD,
            'user_info[password_confirm]' => 'autre-chose',
        ]);

        self::assertResponseStatusCodeSame(422);
        $reloaded = $this->reload(User::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($this->passwordIsValid($reloaded, self::PASSWORD));
    }

    public function testInvalidEmailIsRefusedAndNotSaved(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->submitProfile(['user_info[email]' => 'pas-un-email']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('membre@example.com', $this->reload(User::class, $user->getId())?->getEmail());
    }

    public function testAccountDeletionAnonymisesAndLogsOut(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/user/manage');
        $form = $crawler->filter('form[action="/user/delete"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/');

        $deleted = $this->reload(User::class, $user->getId());
        self::assertNotNull($deleted);
        self::assertStringEndsWith('@deleteduser.fr', (string) $deleted->getEmail());
        self::assertSame('Anonyme', $deleted->getName());
        self::assertFalse($deleted->isVerified());
        self::assertFalse($this->passwordIsValid($deleted, self::PASSWORD), 'L\'ancien mot de passe ne doit plus fonctionner');

        // Il est bien déconnecté : l'espace membre le renvoie vers la connexion.
        $this->client->request('GET', '/user/manage');
        self::assertResponseRedirects('/login');
    }
}
