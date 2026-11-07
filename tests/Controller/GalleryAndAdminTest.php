<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Tests\FunctionalTestCase;

final class GalleryAndAdminTest extends FunctionalTestCase
{
    public function testGalleryOnlyShowsValidatedPhotos(): void
    {
        $theme = $this->createTheme();
        $visible = $this->createPhoto($this->createUser('a@example.com', name: 'Visible'), $theme);
        $hidden = $this->createPhoto($this->createUser('b@example.com', name: 'Cachee'), $theme, visible: false);

        $crawler = $this->client->request('GET', '/gallery');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('[data-vote-photo-value="%d"]', $visible->getId())));
        self::assertCount(0, $crawler->filter(sprintf('[data-vote-photo-value="%d"]', $hidden->getId())));
    }

    public function testGalleryWithoutContestRedirectsHome(): void
    {
        $this->client->request('GET', '/gallery');

        self::assertResponseRedirects('/');
    }

    public function testHomePageWorksWithoutAnyContest(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testHomePageWorksWhenLastContestHasNoWinner(): void
    {
        $this->createTheme('-60 days', '-31 days');

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testHomePageShowsRealRecentWinners(): void
    {
        $winner = $this->createUser('gagnant@example.com', name: 'Gagnant');
        $theme = $this->createTheme('-40 days', '-5 days', 'Brume');
        $theme->setWinner($winner);
        $photo = $this->createPhoto($winner, $theme);
        $this->createPhoto($this->createUser('autre@example.com', name: 'Autre'), $theme);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $winners = $crawler->filter('.polaroid-mini img');
        self::assertCount(1, $winners);
        self::assertStringContainsString((string) $photo->getName(), (string) $winners->attr('src'));
        self::assertStringNotContainsString('picsum', (string) $crawler->html());
    }

    public function testAdminValidatesAPhotoWithAPostForm(): void
    {
        $theme = $this->createTheme();
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme, visible: false);
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $crawler = $this->client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/photo/accept/%d"]', $photo->getId()))->form());

        self::assertResponseRedirects('/admin/dashboard');
        self::assertTrue($this->reload(Photo::class, $photo->getId())?->isVisible());
    }

    public function testPhotoValidationRefusesGetRequests(): void
    {
        $theme = $this->createTheme();
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme, visible: false);
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $this->client->request('GET', sprintf('/admin/photo/accept/%d', $photo->getId()));

        self::assertResponseStatusCodeSame(405);
        self::assertFalse($this->reload(Photo::class, $photo->getId())?->isVisible());
    }

    public function testMembersCannotAccessAdmin(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/admin/dashboard');

        self::assertResponseStatusCodeSame(403);
    }
}
