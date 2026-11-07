<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use App\Tests\FunctionalTestCase;
use Doctrine\ORM\Tools\SchemaTool;

final class ThemeRepositoryTest extends FunctionalTestCase
{
    private function repository(): ThemeRepository
    {
        return static::getContainer()->get(ThemeRepository::class);
    }

    public function testContestIsStillInProgressOnItsLastDay(): void
    {
        // Ce cas a déjà cassé : le dernier jour du concours n'était plus considéré comme en cours.
        $theme = $this->createTheme('-29 days', 'today');

        self::assertSame($theme->getId(), $this->repository()->findActualTheme()?->getId());
        self::assertNull($this->repository()->findLastFinishedTheme());
    }

    public function testContestIsInProgressOnItsFirstDay(): void
    {
        $theme = $this->createTheme('today', '+29 days');

        self::assertSame($theme->getId(), $this->repository()->findActualTheme()?->getId());
    }

    public function testLastFinishedTheme(): void
    {
        $this->createTheme('-90 days', '-61 days', 'Ancien');
        $last = $this->createTheme('-60 days', 'yesterday', 'Dernier');
        $this->createTheme();

        self::assertSame($last->getId(), $this->repository()->findLastFinishedTheme()?->getId());
        self::assertInstanceOf(Theme::class, $this->repository()->findActualTheme());
    }

    public function testMigrationsMatchTheEntityMapping(): void
    {
        $sql = new SchemaTool($this->em)->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata());

        // Ces deux tables techniques n'ont pas d'entité, c'est normal.
        $sql = array_values(array_filter(
            $sql,
            static fn (string $query): bool => !str_contains($query, 'messenger_messages') && !str_contains($query, 'doctrine_migration_versions'),
        ));

        self::assertSame([], $sql, 'Une migration manque : lancez make:migration');
    }
}
