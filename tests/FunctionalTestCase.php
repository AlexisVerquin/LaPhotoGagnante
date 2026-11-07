<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Photo;
use App\Entity\SurveyChoice;
use App\Entity\Theme;
use App\Entity\User;
use App\Entity\Vote;
use App\Service\PhotoStorage;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Point de départ des tests fonctionnels : une base vide à chaque test et de quoi créer vite des données.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    public const PASSWORD = 'Un-M0t-De-Passe-Solide!';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    /** @var list<string> dossiers d'upload créés par le test, qu'on nettoie à la fin */
    private array $createdDirectories = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        new ORMPurger($this->em)->purge();

        // Pour que les tentatives de connexion d'un test ne bloquent pas le suivant.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->createdDirectories);
        parent::tearDown();
    }

    protected function createUser(
        string $email = 'membre@example.com',
        bool $verified = true,
        bool $admin = false,
        string $name = 'Membre',
    ): User {
        $user = new User()
            ->setEmail($email)
            ->setName($name)
            ->setIsVerified($verified)
            ->setIsActive(true)
            ->setRoles($admin ? ['ROLE_ADMIN'] : []);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Par défaut, un concours en cours qui se termine dans 3 jours, donc ouvert aux votes.
     */
    protected function createTheme(string $start = '-27 days', string $end = '+3 days', string $name = 'Reflets'): Theme
    {
        $directory = 'test_' . uniqid();
        $container = static::getContainer();
        $this->createdDirectories[] = $container->getParameter('app.public_photos_dir') . '/' . $directory;
        $this->createdDirectories[] = $container->getParameter('app.pending_photos_dir') . '/' . $directory;

        $theme = new Theme()
            ->setDateStart(new \DateTime($start))
            ->setDateEnd(new \DateTime($end))
            ->setDirectoryName($directory)
            ->setIsAlreadyActived(true)
            ->setThemeChoice(new SurveyChoice()->setName($name)->setNumberVoter(0));

        $this->em->persist($theme);
        $this->em->flush();

        return $theme;
    }

    protected function createPhoto(User $user, Theme $theme, bool $visible = true, string $createdAt = 'now'): Photo
    {
        $photo = new Photo()
            ->setUser($user)
            ->setTheme($theme)
            ->setIsVisible($visible)
            ->setName(uniqid('photo-') . '.jpg');
        $theme->addPhoto($photo);

        $this->em->persist($photo);
        $this->em->flush();

        // Un vrai fichier, rangé au même endroit que le ferait l'application.
        $storage = static::getContainer()->get(PhotoStorage::class);
        $path = $visible ? $storage->publicPath($photo) : $storage->pendingPath($photo);
        new Filesystem()->dumpFile($path, 'fake-jpeg-' . $photo->getName());

        // created_at est fixé à l'enregistrement, donc on le corrige après si le test veut une autre date.
        if ('now' !== $createdAt) {
            $photo->setCreatedAt(new \DateTimeImmutable($createdAt));
            $this->em->flush();
        }

        return $photo;
    }

    protected function createVote(User $user, Photo $photo): Vote
    {
        $vote = new Vote()
            ->setUser($user)
            ->setPhoto($photo)
            ->setTheme($photo->getTheme())
            ->setDate(new \DateTime());
        $photo->addVote($vote);

        $this->em->persist($vote);
        $this->em->flush();

        return $vote;
    }

    /**
     * Relit l'entité en base, utile quand une requête HTTP vient de la modifier.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    protected function reload(string $class, ?int $id): ?object
    {
        $this->em->clear();

        return $this->em->getRepository($class)->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
