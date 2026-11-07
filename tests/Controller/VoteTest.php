<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vote;
use App\Tests\FunctionalTestCase;

final class VoteTest extends FunctionalTestCase
{
    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/gallery');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('[data-vote-csrf-value]')->first()->attr('data-vote-csrf-value');
    }

    /**
     * @return array<string, mixed>
     */
    private function vote(int $photoId, ?string $token): array
    {
        $this->client->request('POST', '/vote', ['photoId' => $photoId], [], $token ? ['HTTP_X_CSRF_TOKEN' => $token] : []);

        return $this->jsonResponse();
    }

    public function testVoteIsRecordedAndCanBeMovedToAnotherPhoto(): void
    {
        $theme = $this->createTheme();
        $photoA = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $photoB = $this->createPhoto($this->createUser('b@example.com', name: 'B'), $theme);
        $voter = $this->createUser('voter@example.com', name: 'Votant');
        $this->client->loginUser($voter);
        $token = $this->csrfToken();

        $data = $this->vote((int) $photoA->getId(), $token);
        self::assertTrue($data['success']);
        self::assertSame(1, $data['newVoteCount']);

        $data = $this->vote((int) $photoB->getId(), $token);
        self::assertTrue($data['success']);
        self::assertSame($photoA->getId(), $data['oldPhotoId']);
        self::assertSame(0, $data['oldVoteCount']);
        self::assertSame(1, $data['newVoteCount']);

        // Changer d'avis ne crée pas un deuxième vote.
        self::assertSame(1, $this->em->getRepository(Vote::class)->count(['user' => $voter->getId()]));
    }

    public function testVoteWithoutCsrfTokenIsRejected(): void
    {
        $theme = $this->createTheme();
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $this->client->loginUser($this->createUser('voter@example.com', name: 'Votant'));

        $data = $this->vote((int) $photo->getId(), null);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($data['success']);
        self::assertSame(0, $this->em->getRepository(Vote::class)->count());
    }

    public function testVoteRequiresPost(): void
    {
        $this->client->request('GET', '/vote');

        self::assertResponseStatusCodeSame(405);
    }

    public function testAnonymousCannotVote(): void
    {
        $theme = $this->createTheme();
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $token = $this->csrfToken();

        $data = $this->vote((int) $photo->getId(), $token);

        self::assertFalse($data['success']);
        self::assertSame(0, $this->em->getRepository(Vote::class)->count());
    }

    public function testCannotVoteForAPhotoNotYetValidated(): void
    {
        $theme = $this->createTheme();
        $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $hidden = $this->createPhoto($this->createUser('b@example.com', name: 'B'), $theme, visible: false);
        $this->client->loginUser($this->createUser('voter@example.com', name: 'Votant'));

        $this->vote((int) $hidden->getId(), $this->csrfToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotVoteForAPhotoOfAPreviousContest(): void
    {
        $old = $this->createTheme('-60 days', '-31 days', 'Ancien');
        $oldPhoto = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $old);
        $current = $this->createTheme();
        $this->createPhoto($this->createUser('b@example.com', name: 'B'), $current);
        $this->client->loginUser($this->createUser('voter@example.com', name: 'Votant'));

        $this->vote((int) $oldPhoto->getId(), $this->csrfToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testVotesAreClosedUntilSixDaysBeforeTheEnd(): void
    {
        $theme = $this->createTheme('-5 days', '+20 days');
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $this->client->loginUser($this->createUser('voter@example.com', name: 'Votant'));

        $data = $this->vote((int) $photo->getId(), $this->csrfToken());

        self::assertFalse($data['success']);
        self::assertStringContainsString('Les votes seront activés', $data['message']);
    }

    public function testUnverifiedMemberCannotVote(): void
    {
        $theme = $this->createTheme();
        $photo = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $theme);
        $this->client->loginUser($this->createUser('voter@example.com', verified: false, name: 'Votant'));

        $this->vote((int) $photo->getId(), $this->csrfToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberCanVoteForTheirOwnPhoto(): void
    {
        // On a décidé de laisser les membres voter pour leur propre photo.
        $theme = $this->createTheme();
        $author = $this->createUser('a@example.com', name: 'A');
        $photo = $this->createPhoto($author, $theme);
        $this->client->loginUser($author);

        $data = $this->vote((int) $photo->getId(), $this->csrfToken());

        self::assertTrue($data['success']);
        self::assertInstanceOf(User::class, $this->reload(User::class, $author->getId()));
    }
}
