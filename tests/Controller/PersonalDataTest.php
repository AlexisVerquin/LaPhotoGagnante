<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Tests\FunctionalTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * Export des données, âge minimum à l'inscription et information des membres en cas de changement des textes.
 */
final class PersonalDataTest extends FunctionalTestCase
{
    public function testMemberDownloadsAllTheirData(): void
    {
        $member = $this->createUser('membre@example.com', name: 'Membre');
        $theme = $this->createTheme(name: 'Reflets');
        $this->createPhoto($member, $theme);
        $this->createVote($member, $this->createPhoto($this->createUser('autre@example.com', name: 'Autre'), $theme));
        $this->em->persist(new Contact()->setEmail('membre@example.com')->setMessage('Ma question')
            ->setType(Contact::TYPE_CONTACT)->setOrigin(0)->setUserId($member->getId()));
        $this->em->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', '/user/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringStartsWith('attachment; filename=mes-donnees-laphotogagnante-', (string) $this->client->getResponse()->headers->get('content-disposition'));
        $data = $this->jsonResponse();
        self::assertSame('membre@example.com', $data['compte']['email']);
        self::assertCount(1, $data['photos']);
        self::assertSame('Reflets', $data['photos'][0]['concours']);
        self::assertSame('Autre', $data['votes'][0]['photo_de']);
        self::assertSame('Ma question', $data['messages_de_contact'][0]['message']);
    }

    public function testExportRequiresLogin(): void
    {
        $this->client->request('GET', '/user/export');

        self::assertResponseRedirects('/login');
    }

    /**
     * @param array<string, string|bool> $extra
     */
    private function register(array $extra): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form[name="registration_form"]')->form();
        $values = [
            'registration_form[email]' => 'nouveau@example.com',
            'registration_form[name]' => 'Nouveau',
            'registration_form[plainPassword]' => 'Un-Tres-Bon-Mot-De-Passe-42!',
        ];
        foreach (['agreeTerms' => true, 'ageConfirmation' => (bool) ($extra['age'] ?? false)] as $field => $checked) {
            $checkbox = $form['registration_form[' . $field . ']'];
            self::assertInstanceOf(ChoiceFormField::class, $checkbox);
            $checked ? $checkbox->tick() : $checkbox->untick();
        }
        $this->client->submit($form, $values);
    }

    public function testRegistrationRequiresTheMinimumAge(): void
    {
        $this->register(['age' => false]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', '15 ans et plus');
        self::assertSame(0, $this->em->getRepository(User::class)->count());

        $this->register(['age' => true]);

        self::assertSame(1, $this->em->getRepository(User::class)->count());
    }

    public function testMembersAreToldWhenTheTermsChange(): void
    {
        $this->createUser('abonne@example.com', name: 'Abonné');
        $this->createUser('desabonne@example.com', name: 'Désabonné')->setNotifyNewTheme(false);
        $this->createUser('suspendu@example.com', name: 'Suspendu')->setIsActive(false);
        $this->createUser('pas-verifie@example.com', verified: false, name: 'Pas vérifié');
        $this->em->flush();

        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('app:legal:notify-update'));
        $tester->execute(['summary' => 'Les photos gagnantes sont gardées un an.']);

        $tester->assertCommandIsSuccessful();
        $emails = array_values(array_filter(static::getMailerMessages(), static fn ($m) => $m instanceof TemplatedEmail));
        $recipients = array_map(static fn (TemplatedEmail $e): string => $e->getTo()[0]->getAddress(), $emails);
        sort($recipients);
        self::assertSame(['abonne@example.com', 'desabonne@example.com'], $recipients, 'Information obligatoire : même les désabonnés des annonces');
        self::assertStringContainsString('gardées un an', (string) $emails[0]->getHtmlBody());
    }
}
