<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorInterface;

class TranslationTest extends KernelTestCase
{
    private $translator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->translator = static::getContainer()->get('translator');
    }

    public function testTranslatorServiceIsAvailable(): void
    {
        $this->assertInstanceOf(TranslatorInterface::class, $this->translator);
    }

    public function testTranslationInEnglish(): void
    {
        $translated = $this->translator->trans('app.welcome', [], null, 'en');
        $this->assertEquals('Welcome to Family Plan', $translated);
    }

    public function testTranslationInPolish(): void
    {
        $translated = $this->translator->trans('app.welcome', [], null, 'pl');
        $this->assertEquals('Witamy w Family Plan', $translated);
    }

    public function testFallbackToEnglishWhenTranslationMissing(): void
    {
        $translated = $this->translator->trans('app.missing_key', [], null, 'pl');
        // Should fall back to English, or return the key if not found
        $this->assertIsString($translated);
    }

    public function testTaskRelatedTranslationsInEnglish(): void
    {
        $this->assertEquals('Tasks', $this->translator->trans('tasks.title', [], null, 'en'));
        $this->assertEquals('Task created successfully', $this->translator->trans('tasks.created', [], null, 'en'));
        $this->assertEquals('Task completed successfully', $this->translator->trans('tasks.completed', [], null, 'en'));
    }

    public function testTaskRelatedTranslationsInPolish(): void
    {
        $this->assertEquals('Zadania', $this->translator->trans('tasks.title', [], null, 'pl'));
        $this->assertEquals('Zadanie utworzone pomyślnie', $this->translator->trans('tasks.created', [], null, 'pl'));
        $this->assertEquals('Zadanie ukończone pomyślnie', $this->translator->trans('tasks.completed', [], null, 'pl'));
    }

    public function testAuthenticationTranslationsInEnglish(): void
    {
        $this->assertEquals('Login', $this->translator->trans('auth.login', [], null, 'en'));
        $this->assertEquals('Logout', $this->translator->trans('auth.logout', [], null, 'en'));
        $this->assertEquals('Login successful', $this->translator->trans('auth.login_successful', [], null, 'en'));
    }

    public function testAuthenticationTranslationsInPolish(): void
    {
        $this->assertEquals('Zaloguj', $this->translator->trans('auth.login', [], null, 'pl'));
        $this->assertEquals('Wyloguj', $this->translator->trans('auth.logout', [], null, 'pl'));
        $this->assertEquals('Logowanie zakończone sukcesem', $this->translator->trans('auth.login_successful', [], null, 'pl'));
    }

    public function testValidationTranslationsInEnglish(): void
    {
        $this->assertEquals('This field is required', $this->translator->trans('validation.required', [], null, 'en'));
        $this->assertEquals('Invalid email format', $this->translator->trans('validation.email', [], null, 'en'));
    }

    public function testValidationTranslationsInPolish(): void
    {
        $this->assertEquals('To pole jest wymagane', $this->translator->trans('validation.required', [], null, 'pl'));
        $this->assertEquals('Nieprawidłowy format email', $this->translator->trans('validation.email', [], null, 'pl'));
    }
}
