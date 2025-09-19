<?php

namespace App\Service;

use App\Repository\LanguageRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion avancée des traductions - Système Oragon
 * 
 * Fonctionnalités :
 * - Gestion centralisée des traductions par domaine et langue
 * - Synchronisation automatique avec les langues actives
 * - Statistiques de progression des traductions
 * - Interface d'administration complète
 * - Cache optimisé pour les performances
 * 
 * @author Prudence ASSOGBA
 */
class TranslationManagerService
{
    private Filesystem $filesystem;
    private array $cache = [];

    public function __construct(
        private KernelInterface $kernel,
        private LanguageRepository $languageRepository,
        private EntityManagerInterface $entityManager
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Récupère les traductions pour un domaine et une langue donnée
     */
    public function getTranslations(string $domain, string $locale): array
    {
        $cacheKey = "{$domain}_{$locale}";
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $filePath = $this->getTranslationFilePath($domain, $locale);
        
        if (!$this->filesystem->exists($filePath)) {
            $this->createTranslationFile($domain, $locale);
            return [];
        }

        $translations = Yaml::parseFile($filePath) ?? [];
        $this->cache[$cacheKey] = $translations;
        
        return $translations;
    }

    /**
     * Sauvegarde les traductions pour un domaine et une langue
     */
    public function saveTranslations(string $domain, string $locale, array $translations): bool
    {
        try {
            $filePath = $this->getTranslationFilePath($domain, $locale);
            $yamlContent = Yaml::dump($translations, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            
            $this->filesystem->dumpFile($filePath, $yamlContent);
            
            // Mise à jour du cache
            $cacheKey = "{$domain}_{$locale}";
            $this->cache[$cacheKey] = $translations;
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Synchronise les fichiers de traduction avec les langues actives
     */
    public function synchronizeWithLanguages(string $domain = 'messages'): void
    {
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
        
        if (!$defaultLanguage) {
            throw new \RuntimeException('Aucune langue par défaut définie');
        }

        $defaultTranslations = $this->getTranslations($domain, $defaultLanguage->getCode());
        
        foreach ($activeLanguages as $language) {
            $locale = $language->getCode();
            
            if ($locale === $defaultLanguage->getCode()) {
                continue; // Passer la langue par défaut
            }
            
            $existingTranslations = $this->getTranslations($domain, $locale);
            $mergedTranslations = $this->mergeTranslations($defaultTranslations, $existingTranslations);
            
            $this->saveTranslations($domain, $locale, $mergedTranslations);
        }
    }

    /**
     * Génère des statistiques de progression des traductions
     */
    public function getTranslationStats(string $domain = 'messages'): array
    {
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
        
        if (!$defaultLanguage) {
            return [];
        }
        
        $defaultTranslations = $this->getTranslations($domain, $defaultLanguage->getCode());
        $totalKeys = $this->countTranslationKeys($defaultTranslations);
        
        $stats = [];
        
        foreach ($activeLanguages as $language) {
            $locale = $language->getCode();
            $translations = $this->getTranslations($domain, $locale);
            $translatedKeys = $this->countTranslatedKeys($translations);
            
            $completionPercentage = $totalKeys > 0 ? round(($translatedKeys / $totalKeys) * 100, 2) : 0;
            
            $stats[] = [
                'language' => [
                    'code' => $locale,
                    'name' => $language->getName(),
                    'isDefault' => $language->isIsDefault()
                ],
                'total_keys' => $totalKeys,
                'translated_keys' => $translatedKeys,
                'missing_keys' => $totalKeys - $translatedKeys,
                'completion_percentage' => $completionPercentage,
                'status' => $this->getTranslationStatus($completionPercentage)
            ];
        }
        
        return $stats;
    }

    /**
     * Aplati les traductions pour l'interface d'administration
     */
    public function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($translations as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenTranslations($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Reconstitue la structure des traductions depuis un tableau aplati
     */
    public function unflattenTranslations(array $flatTranslations): array
    {
        $result = [];
        
        foreach ($flatTranslations as $key => $value) {
            $this->setNestedValue($result, $key, $value);
        }
        
        return $result;
    }

    /**
     * Exporte les traductions au format YAML
     */
    public function exportTranslations(string $domain, string $locale): string
    {
        $translations = $this->getTranslations($domain, $locale);
        return Yaml::dump($translations, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * Importe les traductions depuis un contenu YAML
     */
    public function importTranslations(string $domain, string $locale, string $yamlContent): bool
    {
        try {
            $translations = Yaml::parse($yamlContent);
            
            if (!is_array($translations)) {
                throw new \InvalidArgumentException('Le contenu YAML doit être un tableau');
            }
            
            return $this->saveTranslations($domain, $locale, $translations);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère tous les domaines de traduction disponibles
     */
    public function getAvailableDomains(): array
    {
        $translationDir = $this->getTranslationDirectory();
        $domains = [];
        
        if (!$this->filesystem->exists($translationDir)) {
            return ['messages'];
        }
        
        $files = glob($translationDir . '/*.yaml');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(.+)\.([a-z]{2})\.yaml$/', $filename, $matches)) {
                $domain = $matches[1];
                if (!in_array($domain, $domains)) {
                    $domains[] = $domain;
                }
            }
        }
        
        return empty($domains) ? ['messages'] : $domains;
    }

    /**
     * Valide la structure des traductions
     */
    public function validateTranslations(array $translations): array
    {
        $errors = [];
        
        $this->validateTranslationsRecursive($translations, '', $errors);
        
        return $errors;
    }

    // --- Méthodes privées ---

    private function getTranslationDirectory(): string
    {
        return $this->kernel->getProjectDir() . '/translations';
    }

    private function getTranslationFilePath(string $domain, string $locale): string
    {
        return $this->getTranslationDirectory() . "/{$domain}.{$locale}.yaml";
    }

    private function createTranslationFile(string $domain, string $locale): void
    {
        $filePath = $this->getTranslationFilePath($domain, $locale);
        $directory = dirname($filePath);
        
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }
        
        $this->filesystem->dumpFile($filePath, '# Traductions pour ' . $locale . "\n");
    }

    private function mergeTranslations(array $default, array $existing): array
    {
        foreach ($default as $key => $value) {
            if (is_array($value)) {
                $existing[$key] = $this->mergeTranslations(
                    $value, 
                    $existing[$key] ?? []
                );
            } elseif (!isset($existing[$key]) || empty($existing[$key])) {
                $existing[$key] = ''; // Clé vide à traduire
            }
        }
        
        return $existing;
    }

    private function countTranslationKeys(array $translations): int
    {
        $count = 0;
        
        foreach ($translations as $value) {
            if (is_array($value)) {
                $count += $this->countTranslationKeys($value);
            } else {
                $count++;
            }
        }
        
        return $count;
    }

    private function countTranslatedKeys(array $translations): int
    {
        $count = 0;
        
        foreach ($translations as $value) {
            if (is_array($value)) {
                $count += $this->countTranslatedKeys($value);
            } elseif (!empty(trim($value))) {
                $count++;
            }
        }
        
        return $count;
    }

    private function getTranslationStatus(float $percentage): string
    {
        if ($percentage >= 100) {
            return 'complete';
        } elseif ($percentage >= 80) {
            return 'good';
        } elseif ($percentage >= 50) {
            return 'average';
        } elseif ($percentage > 0) {
            return 'poor';
        } else {
            return 'empty';
        }
    }

    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }

    private function validateTranslationsRecursive(array $translations, string $prefix, array &$errors): void
    {
        foreach ($translations as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (!is_string($key) || empty($key)) {
                $errors[] = "Clé invalide: '{$fullKey}'";
                continue;
            }
            
            if (is_array($value)) {
                $this->validateTranslationsRecursive($value, $fullKey, $errors);
            } elseif (!is_string($value)) {
                $errors[] = "Valeur non-string pour la clé '{$fullKey}'";
            }
        }
    }
}
