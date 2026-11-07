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
        self::assertSame(1, $this->jsonResponse()['vote']);

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
}
