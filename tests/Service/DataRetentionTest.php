<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contact;
use App\Entity\Photo;
use App\Entity\ResetPasswordRequest;
use App\Entity\Theme;
use App\Entity\User;
use App\Entity\Vote;
use App\Service\DataEraser;
use App\Service\DataRetention;
use App\Service\PhotoStorage;
use App\Tests\FunctionalTestCase;

/**
 * Les durées de conservation annoncées dans la politique de confidentialité.
 */
final class DataRetentionTest extends FunctionalTestCase
{
    /**
     * @return array{photos: int, messages: int, accounts: int}
     */
    private function purge(bool $dryRun = false): array
    {
        return static::getContainer()->get(DataRetention::class)->purge(dryRun: $dryRun);
    }

    private function photoExists(?int $id): bool
    {
        return null !== $this->em->getRepository(Photo::class)->find($id);
    }

    private function createContact(string $email, ?string $closedAgo = null, string $createdAgo = 'now'): Contact
    {
        $contact = new Contact()
            ->setEmail($email)
            ->setMessage('Bonjour, une question sur le concours.')
            ->setType(Contact::TYPE_CONTACT)
            ->setOrigin(0)
            ->setClosedAt(null !== $closedAgo ? new \DateTimeImmutable($closedAgo) : null);
        $this->em->persist($contact);
        $this->em->flush();
        $contact->setCreatedAt(new \DateTimeImmutable($createdAgo));
        $this->em->flush();

        return $contact;
    }

    public function testNonWinningPhotosAreDeletedOnceTheWinnerIsKnown(): void
    {
        $winner = $this->createUser('gagnant@example.com', name: 'Gagnant');
        $theme = $this->createTheme('-40 days', '-2 days');
        $theme->setWinner($winner);
        $winningPhoto = $this->createPhoto($winner, $theme);
        $otherPhoto = $this->createPhoto($this->createUser('autre@example.com', name: 'Autre'), $theme);
        $this->createVote($this->createUser('votant@example.com', name: 'Votant'), $otherPhoto);
        $otherFile = static::getContainer()->get(PhotoStorage::class)->publicPath($otherPhoto);
        [$winningId, $otherId] = [$winningPhoto->getId(), $otherPhoto->getId()];

        $counts = $this->purge();

        self::assertSame(1, $counts['photos']);
        self::assertTrue($this->photoExists($winningId), 'La photo gagnante est gardée un an');
        self::assertFalse($this->photoExists($otherId));
        self::assertFileDoesNotExist($otherFile);
        self::assertSame(0, $this->em->getRepository(Vote::class)->count(), 'Les votes partent avec la photo');
    }

    public function testWinningPhotoIsDeletedAfterOneYear(): void
    {
        $winner = $this->createUser();
        $theme = $this->createTheme('-420 days', '-390 days');
        $theme->setWinner($winner);
        $photoId = $this->createPhoto($winner, $theme)->getId();

        $this->purge();

        self::assertFalse($this->photoExists($photoId));
        self::assertNotNull($this->reload(Theme::class, $theme->getId())?->getWinner(), 'Le palmarès reste');
    }

    public function testPhotosWaitAWeekWhenNoWinnerCouldBeDesignated(): void
    {
        $recent = $this->createPhoto($this->createUser('a@example.com', name: 'A'), $this->createTheme('-30 days', '-3 days', 'Récent'))->getId();
        $old = $this->createPhoto($this->createUser('b@example.com', name: 'B'), $this->createTheme('-40 days', '-10 days', 'Ancien'))->getId();

        $this->purge();

        self::assertTrue($this->photoExists($recent), 'Encore dans le délai de grâce');
        self::assertFalse($this->photoExists($old));
    }

    public function testCurrentContestIsNeverTouched(): void
    {
        $photoId = $this->createPhoto($this->createUser(), $this->createTheme())->getId();

        $this->purge();

        self::assertTrue($this->photoExists($photoId));
    }

    public function testClosedMessagesAreDeletedAfter24HoursWithTheirReplies(): void
    {
        $old = $this->createContact('ancien@example.com', closedAgo: '-25 hours');
        $reply = new Contact()->setEmail('admin@example.com')->setMessage('Réponse de l\'admin')
            ->setType(Contact::TYPE_REPLY)->setOrigin((int) $old->getId())->setIsView(true);
        $this->em->persist($reply);
        $recent = $this->createContact('recent@example.com', closedAgo: '-1 hour');
        $open = $this->createContact('ouvert@example.com');
        $forgotten = $this->createContact('oublie@example.com', createdAgo: '-400 days');
        $this->em->flush();

        $counts = $this->purge();

        self::assertSame(2, $counts['messages']);
        $this->em->clear();
        $repository = $this->em->getRepository(Contact::class);
        self::assertNull($repository->find($old->getId()));
        self::assertNull($repository->find($reply->getId()), 'La réponse part avec le message');
        self::assertNull($repository->find($forgotten->getId()), 'Jamais clôturé, mais plus d\'un an');
        self::assertNotNull($repository->find($recent->getId()));
        self::assertNotNull($repository->find($open->getId()));
    }

    public function testOldAnonymizedAccountsAreFinallyDeleted(): void
    {
        $anonymized = $this->createUser('userdelete-123@deleteduser.fr', name: 'Anonyme')->setIsActive(false);
        $this->em->flush();
        $anonymizedId = $anonymized->getId();
        $this->createUser('actif@example.com', name: 'Actif');
        $this->createUser('suspendu@example.com', name: 'Suspendu')->setIsActive(false);
        $this->em->flush();

        $this->purge();

        self::assertNull($this->reload(User::class, $anonymizedId));
        self::assertSame(2, $this->em->getRepository(User::class)->count(), 'Un compte suspendu n\'est pas supprimé');
    }

    public function testDryRunDeletesNothing(): void
    {
        $photoId = $this->createPhoto($this->createUser(), $this->createTheme('-40 days', '-10 days'))->getId();

        $counts = $this->purge(dryRun: true);

        self::assertSame(1, $counts['photos']);
        self::assertTrue($this->photoExists($photoId));
    }

    public function testAccountErasureRemovesEverythingButKeepsTheContestHistory(): void
    {
        $member = $this->createUser('membre@example.com', name: 'Membre');
        $other = $this->createUser('autre@example.com', name: 'Autre');
        $pastTheme = $this->createTheme('-60 days', '-31 days', 'Passé');
        $pastTheme->setWinner($member);
        $theme = $this->createTheme();
        $memberPhoto = $this->createPhoto($member, $theme);
        $otherPhoto = $this->createPhoto($other, $theme);
        $this->createVote($member, $otherPhoto);
        $this->createVote($other, $memberPhoto);
        $this->createContact('membre@example.com');
        $this->em->persist(new ResetPasswordRequest($member, new \DateTimeImmutable('+1 hour'), 'selecteur', 'jeton'));
        $this->em->flush();
        $memberFile = static::getContainer()->get(PhotoStorage::class)->publicPath($memberPhoto);
        $memberId = $member->getId();

        static::getContainer()->get(DataEraser::class)->eraseAccount($member);
        $this->em->clear();

        self::assertNull($this->em->getRepository(User::class)->find($memberId));
        self::assertFileDoesNotExist($memberFile);
        self::assertSame(0, $this->em->getRepository(Vote::class)->count());
        self::assertSame(0, $this->em->getRepository(Contact::class)->count());
        self::assertSame(0, $this->em->getRepository(ResetPasswordRequest::class)->count());
        self::assertNotNull($this->em->getRepository(Photo::class)->find($otherPhoto->getId()), 'Les autres membres ne sont pas touchés');
        $history = $this->em->getRepository(Theme::class)->find($pastTheme->getId());
        self::assertNotNull($history);
        self::assertNull($history->getWinner(), 'Le concours reste, sans gagnant');
    }
}
