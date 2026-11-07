<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Theme;
use App\Tests\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SetWinnerCommandTest extends FunctionalTestCase
{
    private function executeSetWinner(): int
    {
        $application = new Application(static::$kernel ?? static::bootKernel());

        return new CommandTester($application->find('app:set-winner'))->execute([]);
    }

    public function testPhotoWithMostVotesWins(): void
    {
        $theme = $this->createTheme('-40 days', '-1 day');
        $alice = $this->createUser('alice@example.com', name: 'Alice');
        $bob = $this->createUser('bob@example.com', name: 'Bob');
        $photoAlice = $this->createPhoto($alice, $theme);
        $photoBob = $this->createPhoto($bob, $theme);
        $this->createVote($this->createUser('v1@example.com', name: 'V1'), $photoBob);
        $this->createVote($this->createUser('v2@example.com', name: 'V2'), $photoBob);
        $this->createVote($this->createUser('v3@example.com', name: 'V3'), $photoAlice);

        self::assertSame(Command::SUCCESS, $this->executeSetWinner());

        self::assertSame($bob->getId(), $this->reload(Theme::class, $theme->getId())?->getWinner()?->getId());
    }

    public function testOnATieTheEarliestPhotoWins(): void
    {
        $theme = $this->createTheme('-40 days', '-1 day');
        $late = $this->createUser('late@example.com', name: 'Tard');
        $early = $this->createUser('early@example.com', name: 'Tot');
        $this->createVote($this->createUser('v1@example.com', name: 'V1'), $this->createPhoto($late, $theme, createdAt: '-5 days'));
        $this->createVote($this->createUser('v2@example.com', name: 'V2'), $this->createPhoto($early, $theme, createdAt: '-20 days'));

        $this->executeSetWinner();

        self::assertSame($early->getId(), $this->reload(Theme::class, $theme->getId())?->getWinner()?->getId());
    }

    public function testPhotosNotValidatedAreIgnored(): void
    {
        $theme = $this->createTheme('-40 days', '-1 day');
        $hiddenAuthor = $this->createUser('hidden@example.com', name: 'Cache');
        $visibleAuthor = $this->createUser('visible@example.com', name: 'Visible');
        $hidden = $this->createPhoto($hiddenAuthor, $theme, visible: false);
        $this->createVote($this->createUser('v1@example.com', name: 'V1'), $hidden);
        $this->createVote($this->createUser('v2@example.com', name: 'V2'), $hidden);
        $this->createPhoto($visibleAuthor, $theme);

        $this->executeSetWinner();

        self::assertSame($visibleAuthor->getId(), $this->reload(Theme::class, $theme->getId())?->getWinner()?->getId());
    }

    public function testTheContestInProgressIsNotTouched(): void
    {
        $current = $this->createTheme();
        $this->createPhoto($this->createUser(), $current);

        self::assertSame(Command::SUCCESS, $this->executeSetWinner());

        self::assertNull($this->reload(Theme::class, $current->getId())?->getWinner());
    }

    public function testExistingWinnerIsKept(): void
    {
        $theme = $this->createTheme('-40 days', '-1 day');
        $winner = $this->createUser('winner@example.com', name: 'Gagnant');
        $theme->setWinner($winner);
        $this->em->flush();
        $this->createVote($this->createUser('v1@example.com', name: 'V1'), $this->createPhoto($this->createUser(), $theme));

        $this->executeSetWinner();

        self::assertSame($winner->getId(), $this->reload(Theme::class, $theme->getId())?->getWinner()?->getId());
    }

    public function testSameMemberCanWinSeveralContests(): void
    {
        $champion = $this->createUser('champion@example.com', name: 'Champion');
        $first = $this->createTheme('-80 days', '-50 days', 'Premier');
        $first->setWinner($champion);
        $this->em->flush();

        $second = $this->createTheme('-40 days', '-1 day', 'Second');
        $this->createPhoto($champion, $second);

        self::assertSame(Command::SUCCESS, $this->executeSetWinner());
        self::assertSame($champion->getId(), $this->reload(Theme::class, $second->getId())?->getWinner()?->getId());
    }

    public function testFailsWhenNoValidatedPhoto(): void
    {
        $this->createTheme('-40 days', '-1 day');

        self::assertSame(Command::FAILURE, $this->executeSetWinner());
    }
}
