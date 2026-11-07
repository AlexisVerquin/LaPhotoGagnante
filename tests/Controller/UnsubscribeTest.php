<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Service\ThemeNotifier;
use App\Tests\FunctionalTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

final class UnsubscribeTest extends FunctionalTestCase
{
    private function unsubscribeUrl(User $user): string
    {
        $url = static::getContainer()->get(ThemeNotifier::class)->unsubscribeUrl($user);

        return (string) parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
    }

    public function testClickingTheLinkAsksForConfirmationFirst(): void
    {
        $user = $this->createUser();

        $this->client->request('GET', $this->unsubscribeUrl($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ne plus recevoir');
        self::assertTrue($this->reload(User::class, $user->getId())?->isNotifyNewTheme(), 'Un simple clic ne désinscrit pas');
    }

    public function testConfirmingUnsubscribes(): void
    {
        $user = $this->createUser();

        $this->client->request('POST', $this->unsubscribeUrl($user), ['List-Unsubscribe' => 'One-Click']);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->reload(User::class, $user->getId())?->isNotifyNewTheme());
    }

    public function testATamperedLinkIsRefused(): void
    {
        $victim = $this->createUser('victime@example.com', name: 'Victime');
        $attacker = $this->createUser('pirate@example.com', name: 'Pirate');
        $forged = str_replace('/' . $attacker->getId() . '?', '/' . $victim->getId() . '?', $this->unsubscribeUrl($attacker));

        $this->client->request('POST', $forged);

        self::assertResponseStatusCodeSame(404);
        self::assertTrue($this->reload(User::class, $victim->getId())?->isNotifyNewTheme());
    }

    public function testMembersCanOptOutFromTheirProfile(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/user/manage');
        $form = $crawler->filter('form[name="user_info"]')->form();
        $checkbox = $form['user_info[notifyNewTheme]'];
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);
        $checkbox->untick();
        $this->client->submit($form);

        self::assertResponseRedirects('/user/manage');
        self::assertFalse($this->reload(User::class, $user->getId())?->isNotifyNewTheme());
    }

    public function testAdminClosesAContactRequest(): void
    {
        $contact = new Contact()->setEmail('visiteur@example.com')->setMessage('Bonjour, une question.')
            ->setType(Contact::TYPE_CONTACT)->setOrigin(0);
        $this->em->persist($contact);
        $this->em->flush();
        $this->client->loginUser($this->createUser('admin@example.com', admin: true, name: 'Admin'));

        $crawler = $this->client->request('GET', '/admin/dashboard');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/dashboard/contact/close/%d"]', $contact->getId()))->form());

        self::assertResponseRedirects('/admin/dashboard');
        self::assertNotNull($this->reload(Contact::class, $contact->getId())?->getClosedAt());

        $this->client->request('GET', sprintf('/admin/dashboard/contact/close/%d', $contact->getId()));
        self::assertResponseStatusCodeSame(405);
    }
}
