<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Entity\User;
use App\Tests\FunctionalTestCase;

/**
 * CGU § 7 : refuser ou retirer une photo, suspendre ou supprimer un compte.
 */
final class ModerationTest extends FunctionalTestCase
{
    private function loginAsAdmin(): User
    {
        $admin = $this->createUser('admin@example.com', admin: true, name: 'Admin');
        $this->client->loginUser($admin);

        return $admin;
    }

    public function testAdminRefusesAPendingPhoto(): void
    {
        $photoId = $this->createPhoto($this->createUser(), $this->createTheme(), visible: false)->getId();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/dashboard');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/photo/delete/%d"]', $photoId))->form());

        self::assertResponseRedirects('/admin/dashboard');
        self::assertNull($this->em->getRepository(Photo::class)->find($photoId));
    }

    public function testAdminRemovesAPublishedPhotoFromTheGallery(): void
    {
        $photoId = $this->createPhoto($this->createUser(), $this->createTheme())->getId();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/gallery');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/photo/delete/%d"]', $photoId))->form());

        self::assertResponseRedirects('/gallery');
        self::assertNull($this->em->getRepository(Photo::class)->find($photoId));
    }

    public function testMembersDoNotSeeOrUseModerationTools(): void
    {
        $photo = $this->createPhoto($this->createUser('auteur@example.com', name: 'Auteur'), $this->createTheme());
        $this->client->loginUser($this->createUser('membre@example.com', name: 'Membre'));

        $crawler = $this->client->request('GET', '/gallery');
        self::assertCount(0, $crawler->filter('form[action^="/admin/photo/delete/"]'));

        $this->client->request('POST', sprintf('/admin/photo/delete/%d', $photo->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuspendedMemberCannotLogIn(): void
    {
        $member = $this->createUser('membre@example.com', name: 'Membre');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/dashboard/members');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/dashboard/members/%d/suspend"]', $member->getId()))->form());
        self::assertFalse($this->reload(User::class, $member->getId())?->isActive());

        $this->client->request('GET', '/logout');
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form button[type="submit"]')->form(), [
            '_username' => 'membre@example.com',
            '_password' => self::PASSWORD,
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('#login-error', 'suspendu');
    }

    public function testMemberSuspendedWhileLoggedInIsLoggedOut(): void
    {
        $member = $this->createUser();
        $this->client->loginUser($member);
        $member->setIsActive(false);
        $this->em->flush();

        $this->client->request('GET', '/user/manage');

        self::assertResponseRedirects('/login');
    }

    public function testAdminReactivatesAndDeletesAMember(): void
    {
        $member = $this->createUser('membre@example.com', name: 'Membre')->setIsActive(false);
        $this->em->flush();
        $memberId = $member->getId();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/dashboard/members');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/dashboard/members/%d/suspend"]', $memberId))->form());
        self::assertTrue($this->reload(User::class, $memberId)?->isActive());

        $crawler = $this->client->request('GET', '/admin/dashboard/members');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/dashboard/members/%d/delete"]', $memberId))->form());
        self::assertNull($this->reload(User::class, $memberId));
    }

    public function testAdminCannotSuspendThemself(): void
    {
        $admin = $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/dashboard/members');
        self::assertCount(0, $crawler->filter(sprintf('form[action="/admin/dashboard/members/%d/suspend"]', $admin->getId())));

        $this->client->request('POST', sprintf('/admin/dashboard/members/%d/suspend', $admin->getId()), ['_token' => 'peu-importe']);
        self::assertTrue($this->reload(User::class, $admin->getId())?->isActive());
    }
}
