<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleListener implements EventSubscriberInterface
{
    private const SUPPORTED_LOCALES = ['en', 'pl'];
    
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'pl')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Try to get locale from Accept-Language header
        if (!$request->attributes->get('_locale')) {
            $preferredLanguage = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
            $locale = $preferredLanguage ?: $this->defaultLocale;
            $request->setLocale($locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
