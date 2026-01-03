<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\EventListener;

use App\Shared\Infrastructure\EventListener\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleListenerTest extends TestCase
{
    private LocaleListener $listener;

    protected function setUp(): void
    {
        $this->listener = new LocaleListener('pl');
    }

    public function testSetsLocaleFromAcceptLanguageHeaderToEnglish(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
        
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->onKernelRequest($event);

        $this->assertEquals('en', $request->getLocale());
    }

    public function testSetsLocaleFromAcceptLanguageHeaderToPolish(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'pl-PL,pl;q=0.9');
        
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->onKernelRequest($event);

        $this->assertEquals('pl', $request->getLocale());
    }

    public function testUsesDefaultLocaleWhenNoAcceptLanguageHeader(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->remove('Accept-Language');
        
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->onKernelRequest($event);

        $this->assertEquals('pl', $request->getLocale());
    }

    public function testUsesDefaultLocaleForUnsupportedLanguage(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9');
        
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->onKernelRequest($event);

        $this->assertEquals('pl', $request->getLocale());
    }

    public function testDoesNotOverrideExistingLocaleAttribute(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept-Language', 'pl-PL,pl;q=0.9');
        $request->attributes->set('_locale', 'en');
        
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->listener->onKernelRequest($event);

        // Locale should remain 'en' because _locale attribute was already set
        $this->assertEquals('en', $request->getLocale());
    }
}
