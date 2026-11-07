<?php

// src/EventSubscriber/LocaleSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @param list<string> $availableLocales
     */
    public function __construct(
        private string $defaultLocale = 'en',
        private array $availableLocales = ['en', 'fr'],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $locale = $request->getSession()->get('_locale');
        if (is_string($locale) && in_array($locale, $this->availableLocales, true)) {
            $request->setLocale($locale);

            return;
        }

        $preferredLocale = $request->getPreferredLanguage($this->availableLocales);

        $locale = $preferredLocale ?? $this->defaultLocale;

        $request->setLocale($locale);
        $request->getSession()->set('_locale', $locale);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
