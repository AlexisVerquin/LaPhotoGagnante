<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\FunctionalTestCase;

final class LegalPagesTest extends FunctionalTestCase
{
    public function testLegalPagesAreLinkedFromEveryPage(): void
    {
        $crawler = $this->client->request('GET', '/');

        foreach (['/cgu', '/rgpd', '/mentions-legales'] as $page) {
            self::assertCount(1, $crawler->filter(sprintf('footer a[href="%s"]', $page)), $page);
        }
    }

    public function testLegalPagesShowTheRealSiteAndNoPlaceholderText(): void
    {
        foreach (['/cgu', '/rgpd', '/mentions-legales'] as $page) {
            $html = (string) $this->client->request('GET', $page)->html();

            self::assertResponseIsSuccessful();
            self::assertStringContainsString('laphotogagnante.fr', $html, $page);
            self::assertDoesNotMatchRegularExpression('/site\.com|lorem ipsum|\[Nom du site\]|non applicable|À compléter/i', $html, $page);
        }
    }

    public function testRegistrationAndContactFormsExplainHowDataIsUsed(): void
    {
        foreach (['/register', '/contact'] as $page) {
            $crawler = $this->client->request('GET', $page);

            $links = $crawler->filter('a[href="/rgpd"]')->count() - $crawler->filter('footer a[href="/rgpd"]')->count();
            self::assertGreaterThanOrEqual(1, $links, $page . ' : lien vers la politique à côté du formulaire');
        }
    }

    public function testHostDetailsAreShown(): void
    {
        $this->client->request('GET', '/mentions-legales');

        self::assertSelectorTextContains('#hebergement', 'Scaleway SAS');
        self::assertSelectorTextContains('#hebergement', '75008 Paris');
        // Valeur de .env.test : en production elle vient de .env.prod
        self::assertSelectorTextContains('#hebergement', '01 23 45 67 89');
    }

    public function testEditorAddressFromTheEnvironmentIsShown(): void
    {
        $this->client->request('GET', '/mentions-legales');

        // Valeur de .env.test : en production elle vient de .env.prod, jamais de git
        self::assertSelectorTextContains('#editeur', '59000 Lille');
        self::assertSelectorTextNotContains('#editeur', 'article 6-III-2');
    }
}
