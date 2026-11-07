<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Theme;
use App\Tests\FunctionalTestCase;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;

/**
 * Garde-fous de performance : le nombre de requêtes SQL ne doit pas grandir avec le nombre de photos.
 */
final class QueryCountTest extends FunctionalTestCase
{
    /**
     * @return list<string> les requêtes SQL exécutées par la page
     */
    private function queries(string $url): array
    {
        $this->client->request('GET', $url); // le premier appel compte aussi les insertions du test
        $this->client->enableProfiler();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $collector = $this->client->getProfile()->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return array_column($collector->getQueries()['default'] ?? [], 'sql');
    }

    private function addPhotos(Theme $theme, int $count, int $offset = 0): void
    {
        for ($i = $offset; $i < $offset + $count; ++$i) {
            $photo = $this->createPhoto($this->createUser("auteur$i@example.com", name: "Auteur $i"), $theme);
            $this->createVote($this->createUser("votant$i@example.com", name: "Votant $i"), $photo);
        }
    }

    public function testGalleryCostDoesNotDependOnTheNumberOfPhotos(): void
    {
        $theme = $this->createTheme();
        $this->client->loginUser($this->createUser());
        $this->addPhotos($theme, 3);
        $withThree = count($this->queries('/gallery'));

        // Entre deux requêtes, Symfony vide la mémoire de Doctrine : on recharge le concours
        $theme = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertInstanceOf(Theme::class, $theme);
        $this->addPhotos($theme, 9, offset: 3);
        $withTwelve = count($this->queries('/gallery'));

        self::assertSame($withThree, $withTwelve, 'Une requête par photo est revenue (N+1)');
    }

    public function testCurrentContestIsLoadedOncePerPage(): void
    {
        $this->addPhotos($this->createTheme(), 2);
        $this->client->loginUser($this->createUser());

        foreach (['/', '/gallery'] as $url) {
            $themeQueries = array_filter(
                $this->queries($url),
                static fn (string $sql): bool => str_contains($sql, 'FROM theme') && str_contains($sql, 'date_start <='),
            );
            self::assertCount(1, $themeQueries, $url);
        }
    }
}
