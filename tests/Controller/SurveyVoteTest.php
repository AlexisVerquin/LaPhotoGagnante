<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Survey;
use App\Entity\SurveyChoice;
use App\Tests\FunctionalTestCase;

final class SurveyVoteTest extends FunctionalTestCase
{
    private function createSurvey(string $start, string $end): Survey
    {
        $survey = new Survey()
            ->setName('Prochain thème')
            ->setDateStart(new \DateTime($start))
            ->setDateEnd(new \DateTime($end));

        foreach (['Montagne', 'Mer'] as $name) {
            $choice = new SurveyChoice()->setName($name)->setNumberVoter(0);
            $survey->addSurveyChoice($choice);
            $this->em->persist($choice);
        }

        $this->em->persist($survey);
        $this->em->flush();

        return $survey;
    }

    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/');

        return (string) $crawler->filter('[data-survey-csrf-value]')->first()->attr('data-survey-csrf-value');
    }

    private function vote(SurveyChoice $choice, string $token): void
    {
        $this->client->request('POST', '/voteSurvey', ['id' => $choice->getId()], [], ['HTTP_X_CSRF_TOKEN' => $token]);
    }

    public function testOneVotePerDay(): void
    {
        $this->createTheme();
        $choice = $this->createSurvey('-1 day', '+5 days')->getSurveyChoices()->first();
        self::assertInstanceOf(SurveyChoice::class, $choice);
        $this->client->loginUser($this->createUser());
        $token = $this->csrfToken();

        $this->vote($choice, $token);
        self::assertResponseIsSuccessful();
        $response = $this->jsonResponse();
        self::assertSame(1, $response['vote']);
        self::assertSame(1, $response['total']);
        // Les scores de tous les choix sont renvoyés pour rafraîchir chaque carte.
        self::assertCount(2, $response['choices']);
        self::assertContains(['id' => $choice->getId(), 'vote' => 1], $response['choices']);

        $this->vote($choice, $token);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, $this->reload(SurveyChoice::class, $choice->getId())?->getNumberVoter());
    }

    public function testCannotVoteInAClosedSurvey(): void
    {
        $this->createTheme();
        $this->createSurvey('-1 day', '+5 days');
        $closedChoice = $this->createSurvey('-20 days', '-10 days')->getSurveyChoices()->first();
        self::assertInstanceOf(SurveyChoice::class, $closedChoice);
        $this->client->loginUser($this->createUser());

        $this->vote($closedChoice, $this->csrfToken());

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->reload(SurveyChoice::class, $closedChoice->getId())?->getNumberVoter());
    }

    public function testOnlyTheLeadingChoiceIsMarkedAsLeading(): void
    {
        $this->createTheme();
        $choices = $this->createSurvey('-1 day', '+5 days')->getSurveyChoices();
        $this->client->request('GET', '/');
        self::assertSelectorCount(0, '.theme-vote-card.is-leading', 'Aucun choix en tête tant que personne n\'a voté');

        [$montagne, $mer] = $choices->toArray();
        $mer->setNumberVoter(3);
        $montagne->setNumberVoter(1);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');
        $leading = $crawler->filter('.theme-vote-card.is-leading');
        self::assertCount(1, $leading);
        self::assertStringContainsString('Mer', $leading->text());
        self::assertStringContainsString('En tête', $leading->text());
        self::assertCount(1, $crawler->filter('.theme-vote-card .badge:not([hidden])'));
    }
}
