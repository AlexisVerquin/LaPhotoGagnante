<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Service\PhotoStorage;
use App\Tests\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class PendingPhotoAccessTest extends FunctionalTestCase
{
    private function storage(): PhotoStorage
    {
        return static::getContainer()->get(PhotoStorage::class);
    }

    private function imageUrl(Photo $photo): string
    {
        return sprintf('/photo/%d/image', $photo->getId());
    }

    public function testPendingPhotoIsNotInThePublicFolder(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);

        self::assertFileDoesNotExist($this->storage()->publicPath($photo));
        self::assertFileExists($this->storage()->pendingPath($photo));
    }

    public function testAnonymousCannotSeeAPendingPhoto(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);

        $this->client->request('GET', $this->imageUrl($photo));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnotherMemberCannotSeeAPendingPhoto(): void
    {
        $photo = $this->createPhoto($this->createUser('auteur@example.com', name: 'Auteur'), $this->createTheme(), visible: false);
        $this->client->loginUser($this->createUser('curieux@example.com', name: 'Curieux'));

        $this->client->request('GET', $this->imageUrl($photo));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAuthorCanSeeTheirPendingPhoto(): void
    {
        $author = $this->createUser();
        $photo = $this->createPhoto($author, $this->createTheme(), visible: false);
        $this->client->loginUser($author);

        $this->client->request('GET', $this->imageUrl($photo));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAdminCanSeeAPendingPhoto(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $this->client->request('GET', $this->imageUrl($photo));

        self::assertResponseIsSuccessful();
    }

    public function testValidatedPhotoIsVisibleByEveryone(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme());

        $this->client->request('GET', $this->imageUrl($photo));

        self::assertResponseIsSuccessful();
    }

    public function testValidationMovesTheFileToThePublicFolder(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $crawler = $this->client->request('GET', '/admin/dashboard');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/photo/accept/%d"]', $photo->getId()))->form());

        self::assertTrue($this->reload(Photo::class, $photo->getId())?->isVisible());
        self::assertFileExists($this->storage()->publicPath($photo));
        self::assertFileDoesNotExist($this->storage()->pendingPath($photo));
    }

    public function testValidationFailsCleanlyWhenTheFileIsMissing(): void
    {
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);
        new Filesystem()->remove($this->storage()->pendingPath($photo));
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $crawler = $this->client->request('GET', '/admin/dashboard');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/photo/accept/%d"]', $photo->getId()))->form());

        self::assertResponseRedirects('/admin/dashboard');
        self::assertFalse($this->reload(Photo::class, $photo->getId())?->isVisible());
    }

    public function testCommandMovesOldPendingPhotosOutOfThePublicFolder(): void
    {
        // Une vieille photo en attente, envoyée avant la zone privée, encore dans public/.
        $photo = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false);
        new Filesystem()->copy($this->storage()->pendingPath($photo), $this->storage()->publicPath($photo));
        new Filesystem()->remove($this->storage()->pendingPath($photo));
        $validated = $this->createPhoto($this->createUser('b@example.com', name: 'B'), $photo->getTheme() ?? $this->createTheme());

        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('app:photos:secure-pending'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertFileDoesNotExist($this->storage()->publicPath($photo));
        self::assertFileExists($this->storage()->pendingPath($photo));
        self::assertFileExists($this->storage()->publicPath($validated), 'Les photos validées ne bougent pas');
    }
}
