<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\FunctionalTestCase;

final class SeoTest extends FunctionalTestCase
{
    private const PROD = ['HTTP_HOST' => 'laphotogagnante.fr'];

    public function testPublicPagesHaveTheirOwnTitleDescriptionAndCanonical(): void
    {
        $this->createTheme();
        $seen = [];

        foreach (['/', '/gallery', '/register', '/contact', '/cgu', '/rgpd', '/mentions-legales'] as $page) {
            $crawler = $this->client->request('GET', $page, server: self::PROD);
            self::assertResponseIsSuccessful($page);

            $title = $crawler->filter('title')->text();
            $description = (string) $crawler->filter('meta[name="description"]')->attr('content');
            self::assertNotContains($title, array_column($seen, 0), $page . ' : titre déjà utilisé');
            self::assertNotContains($description, array_column($seen, 1), $page . ' : description déjà utilisée');
            self::assertGreaterThan(50, mb_strlen($description), $page);
            self::assertSame('https://laphotogagnante.fr' . $page, $crawler->filter('link[rel="canonical"]')->attr('href'));
            self::assertCount(0, $crawler->filter('meta[name="robots"]'), $page . ' doit être indexable');
            self::assertCount(1, $crawler->filter('h1'), $page);
            $seen[] = [$title, $description];
        }
    }

    public function testPrivatePagesAndOtherHostsAreNotIndexed(): void
    {
        foreach (['/login', '/reset-password'] as $page) {
            $crawler = $this->client->request('GET', $page, server: self::PROD);
            self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'), $page);
        }

        // La preprod ne doit pas se retrouver dans Google, et son canonical pointe vers le vrai site
        $crawler = $this->client->request('GET', '/cgu', server: ['HTTP_HOST' => 'preprod.laphotogagnante.fr']);
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('https://laphotogagnante.fr/cgu', $crawler->filter('link[rel="canonical"]')->attr('href'));
    }

    public function testRobotsTxtOnlyOpensTheProductionSite(): void
    {
        $this->client->request('GET', '/robots.txt', server: self::PROD);
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('Disallow: /admin', $robots);
        self::assertStringContainsString('Sitemap: https://laphotogagnante.fr/sitemap.xml', $robots);
        self::assertDoesNotMatchRegularExpression('/^Disallow: \/$/m', $robots);

        $this->client->request('GET', '/robots.txt', server: ['HTTP_HOST' => 'preprod.laphotogagnante.fr']);
        self::assertMatchesRegularExpression('/^Disallow: \/$/m', (string) $this->client->getResponse()->getContent());
    }

    public function testSitemapListsPublicPagesAndTheGalleryOnlyDuringAContest(): void
    {
        $this->client->request('GET', '/sitemap.xml');
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        self::assertNotFalse($xml);
        $urls = [];
        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }
        self::assertContains('https://laphotogagnante.fr/cgu', $urls);
        self::assertNotContains('https://laphotogagnante.fr/gallery', $urls);
        self::assertNotContains('https://laphotogagnante.fr/login', $urls);

        $this->createTheme();
        $this->client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('<loc>https://laphotogagnante.fr/gallery</loc>', (string) $this->client->getResponse()->getContent());
    }

    public function testHomeShowsTheRealWinningPhotoAndItsVotes(): void
    {
        $winner = $this->createUser('gagnant@example.com', name: 'Gagnant');
        $theme = $this->createTheme('-40 days', '-5 days', 'Brume');
        $theme->setWinner($winner);
        $photo = $this->createPhoto($winner, $theme);
        $this->createVote($this->createUser('v1@example.com', name: 'V1'), $photo);
        $this->createVote($this->createUser('v2@example.com', name: 'V2'), $photo);
        $this->em->flush();
        $this->em->clear(); // sinon la page relit les collections vides des objets créés ici

        $crawler = $this->client->request('GET', '/');

        self::assertStringNotContainsString('jcvd', (string) $crawler->html());
        self::assertStringContainsString((string) $photo->getName(), (string) $crawler->filter('.countdown-panel .polaroid img')->attr('src'));
        self::assertStringContainsString('2 votes', $crawler->filter('.countdown-panel')->text());
    }
}
