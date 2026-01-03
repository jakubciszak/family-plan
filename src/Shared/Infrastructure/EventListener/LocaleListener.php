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
            $locale = $this->defaultLocale;
            
            // Check if Accept-Language header has a value
            $acceptLanguage = $request->headers->get('Accept-Language');
            if ($acceptLanguage) {
                // Get user's preferred languages
                $preferredLanguages = $request->getLanguages();
                
                // Find first language that we support
                foreach ($preferredLanguages as $preferredLang) {
                    // Extract base language code (e.g., 'en' from 'en_US' or 'en-US')
                    $baseLang = strtolower(substr($preferredLang, 0, 2));
                    if (in_array($baseLang, self::SUPPORTED_LOCALES, true)) {
                        $locale = $baseLang;
                        break;
                    }
                }
            }
            
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
