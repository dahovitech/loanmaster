<?php

namespace App\Service\Localization;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;

/**
 * Service for handling localization and language management
 */
class LocalizationService
{
    private array $supportedLocales;
    private array $cachedLanguages = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        string $supportedLocales
    ) {
        $this->supportedLocales = explode('|', $supportedLocales);
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Get countries list in given locale
     */
    public function getCountries(string $locale = 'en'): array
    {
        return Countries::getNames($locale);
    }

    /**
     * Get languages list in given locale
     */
    public function getLanguages(string $locale = 'en'): array
    {
        return Languages::getNames($locale);
    }

    /**
     * Get active languages from database with caching
     */
    public function getActiveLanguages(): array
    {
        if (empty($this->cachedLanguages)) {
            $this->cachedLanguages = $this->entityManager
                ->getRepository(Language::class)
                ->findBy(['isActive' => true], ['name' => 'ASC']);
        }

        return $this->cachedLanguages;
    }

    /**
     * Get translation files for a given locale
     */
    public function getTranslationFiles(string $locale): array
    {
        $translationsPath = $this->kernel->getProjectDir() . '/translations';
        
        if (!is_dir($translationsPath)) {
            return [];
        }

        $finder = new Finder();
        $files = [];

        try {
            $finder->files()
                ->in($translationsPath)
                ->name("*.$locale.*")
                ->sortByName();

            foreach ($finder as $file) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }
        } catch (\Exception $e) {
            // Log error but return empty array
        }

        return $files;
    }

    /**
     * Validate if locale is supported
     */
    public function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Get country name by code in specific locale
     */
    public function getCountryName(string $countryCode, string $locale = 'en'): ?string
    {
        $countries = $this->getCountries($locale);
        return $countries[strtoupper($countryCode)] ?? null;
    }

    /**
     * Clear language cache
     */
    public function clearLanguageCache(): void
    {
        $this->cachedLanguages = [];
    }
}