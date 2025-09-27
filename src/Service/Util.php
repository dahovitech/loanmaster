<?php

namespace App\Service;

use App\Service\Geo\GeolocationService;
use App\Service\Mail\EmailService;
use App\Service\Localization\LocalizationService;

/**
 * Utility service for common operations - Refactored
 * @deprecated Use specialized services instead
 */
class Util
{
    public function __construct(
        private GeolocationService $geolocationService,
        private EmailService $emailService,
        private LocalizationService $localizationService
    ) {}

    /**
     * @deprecated Use GeolocationService::getUserCountry() instead
     */
    public function getDefaultCountry(): string
    {
        return $this->geolocationService->getUserCountry();
    }

    /**
     * @deprecated Use EmailService::getSetting() instead
     */
    public function getSetting()
    {
        return $this->emailService->getSetting();
    }

    /**
     * Send email using new EmailService
     * @deprecated Use EmailService::sendTemplatedEmail() instead
     */
    public function sender(string $from, string $subject, string $template, array $recipients = [], array $param = []): void
    {
        foreach ($recipients as $recipient) {
            $this->emailService->sendTemplatedEmail($recipient, $subject, $template, $param, $from);
        }
    }

    /**
     * Get locales from active languages
     */
    public function getLocales(): array
    {
        $languages = $this->localizationService->getActiveLanguages();
        return array_map(fn($lang) => $lang->getCode(), $languages);
    }

    /**
     * Get all languages data
     */
    public function getAllLanguages(): array
    {
        $languages = $this->localizationService->getActiveLanguages();
        return array_map(fn($lang) => $lang->toArray(), $languages);
    }

    /**
     * Get locales with names
     */
    public function getLocalesName(): array
    {
        $languages = $this->localizationService->getActiveLanguages();
        $result = [];
        
        foreach ($languages as $language) {
            $name = $this->localizationService->getLanguages()[$language->getCode()] ?? $language->getCode();
            $result[$name] = $language->getCode();
        }
        
        return $result;
    }

    /**
     * Get default language code
     */
    public function getDefaultLanguage(): string
    {
        $languages = $this->localizationService->getActiveLanguages();
        
        foreach ($languages as $language) {
            if ($language->getIsDefault()) {
                return $language->getCode();
            }
        }
        
        return 'fr'; // fallback
    }

    /**
     * Generate unique loan number
     */
    public function generateLoanNumber(): string
    {
        return 'LOAN-' . date('Y') . '-' . uniqid();
    }

    /**
     * Slugify string for URLs
     */
    public function slugify(string $text): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    /**
     * Format amount with currency
     */
    public function formatAmount(float $amount, string $currency = 'EUR'): string
    {
        return number_format($amount, 2, ',', ' ') . ' ' . $currency;
    }
}