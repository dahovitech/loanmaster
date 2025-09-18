<?php

namespace App\Twig;

use App\Service\TranslationManagerService;
use App\Repository\LanguageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * Extension Twig pour le système de traduction Oragon
 * 
 * Fournit des fonctions et filtres Twig pour faciliter l'utilisation 
 * des traductions dans les templates avec le système Oragon.
 * 
 * Fonctions disponibles :
 * - oragon_translate() : Traduit une clé avec le système Oragon
 * - oragon_languages() : Récupère la liste des langues actives
 * - oragon_current_language() : Récupère la langue courante
 * - oragon_translation_stats() : Statistiques des traductions
 * 
 * Filtres disponibles :
 * - oragon_translate : Filtre pour traduire une clé
 * - oragon_fallback : Filtre avec valeur de fallback
 * 
 * @author Prudence ASSOGBA
 */
class TranslationExtension extends AbstractExtension
{
    private ?string $currentLocale = null;

    public function __construct(
        private TranslationManagerService $translationManager,
        private LanguageRepository $languageRepository,
        private RequestStack $requestStack
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oragon_translate', [$this, 'translate']),
            new TwigFunction('oragon_t', [$this, 'translate']), // Alias court
            new TwigFunction('oragon_languages', [$this, 'getLanguages']),
            new TwigFunction('oragon_current_language', [$this, 'getCurrentLanguage']),
            new TwigFunction('oragon_default_language', [$this, 'getDefaultLanguage']),
            new TwigFunction('oragon_translation_stats', [$this, 'getTranslationStats']),
            new TwigFunction('oragon_has_translation', [$this, 'hasTranslation']),
            new TwigFunction('oragon_translation_completion', [$this, 'getTranslationCompletion']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('oragon_translate', [$this, 'translateFilter']),
            new TwigFilter('oragon_t', [$this, 'translateFilter']), // Alias court
            new TwigFilter('oragon_fallback', [$this, 'translateWithFallback']),
            new TwigFilter('oragon_format', [$this, 'formatTranslation']),
        ];
    }

    /**
     * Traduit une clé de traduction avec le système Oragon
     * 
     * @param string $key Clé de traduction (ex: "nav.home")
     * @param string|null $domain Domaine de traduction (défaut: "messages")
     * @param string|null $locale Langue cible (défaut: langue courante)
     * @param array $parameters Paramètres pour le remplacement
     * @param string|null $fallback Valeur de fallback si traduction non trouvée
     * 
     * @return string Texte traduit
     */
    public function translate(
        string $key, 
        ?string $domain = 'messages', 
        ?string $locale = null, 
        array $parameters = [], 
        ?string $fallback = null
    ): string {
        $locale = $locale ?? $this->getCurrentLocale();
        $domain = $domain ?? 'messages';
        
        try {
            $translations = $this->translationManager->getTranslations($domain, $locale);
            $flatTranslations = $this->translationManager->flattenTranslations($translations);
            
            $translation = $flatTranslations[$key] ?? null;
            
            // Si pas de traduction, essayer avec la langue par défaut
            if (empty($translation)) {
                $defaultLanguage = $this->getDefaultLanguage();
                if ($defaultLanguage && $defaultLanguage->getCode() !== $locale) {
                    $defaultTranslations = $this->translationManager->getTranslations($domain, $defaultLanguage->getCode());
                    $defaultFlatTranslations = $this->translationManager->flattenTranslations($defaultTranslations);
                    $translation = $defaultFlatTranslations[$key] ?? null;
                }
            }
            
            // Si toujours pas de traduction, utiliser le fallback ou la clé
            if (empty($translation)) {
                $translation = $fallback ?? $key;
            }
            
            // Remplacement des paramètres
            if (!empty($parameters)) {
                $translation = $this->replaceParameters($translation, $parameters);
            }
            
            return $translation;
            
        } catch (\Exception $e) {
            return $fallback ?? $key;
        }
    }

    /**
     * Filtre de traduction pour utilisation avec le pipe Twig
     * 
     * Usage: {{ "nav.home"|oragon_translate }}
     */
    public function translateFilter(
        string $key, 
        ?string $domain = 'messages', 
        ?string $locale = null, 
        array $parameters = []
    ): string {
        return $this->translate($key, $domain, $locale, $parameters);
    }

    /**
     * Filtre de traduction avec fallback personnalisé
     * 
     * Usage: {{ "nav.home"|oragon_fallback("Accueil") }}
     */
    public function translateWithFallback(
        string $key, 
        string $fallback, 
        ?string $domain = 'messages', 
        ?string $locale = null
    ): string {
        return $this->translate($key, $domain, $locale, [], $fallback);
    }

    /**
     * Format une traduction avec des paramètres avancés
     * 
     * Usage: {{ "welcome.message"|oragon_format({'name': user.name, 'count': items|length}) }}
     */
    public function formatTranslation(string $translation, array $parameters = []): string
    {
        return $this->replaceParameters($translation, $parameters);
    }

    /**
     * Récupère la liste des langues actives
     * 
     * @param bool $includeInactive Inclure les langues inactives
     * @return array Liste des langues
     */
    public function getLanguages(bool $includeInactive = false): array
    {
        $criteria = $includeInactive ? [] : ['isEnabled' => true];
        return $this->languageRepository->findBy($criteria, ['code' => 'ASC']);
    }

    /**
     * Récupère la langue courante
     * 
     * @return object|null Langue courante ou null
     */
    public function getCurrentLanguage(): ?object
    {
        $locale = $this->getCurrentLocale();
        return $this->languageRepository->findOneBy(['code' => $locale, 'isEnabled' => true]);
    }

    /**
     * Récupère la langue par défaut
     * 
     * @return object|null Langue par défaut ou null
     */
    public function getDefaultLanguage(): ?object
    {
        return $this->languageRepository->findOneBy(['isDefault' => true, 'isEnabled' => true]);
    }

    /**
     * Récupère les statistiques de traduction pour un domaine
     * 
     * @param string $domain Domaine de traduction
     * @return array Statistiques de traduction
     */
    public function getTranslationStats(string $domain = 'messages'): array
    {
        try {
            return $this->translationManager->getTranslationStats($domain);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Vérifie si une traduction existe pour une clé
     * 
     * @param string $key Clé de traduction
     * @param string $domain Domaine de traduction
     * @param string|null $locale Langue (défaut: langue courante)
     * @return bool True si la traduction existe
     */
    public function hasTranslation(string $key, string $domain = 'messages', ?string $locale = null): bool
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        try {
            $translations = $this->translationManager->getTranslations($domain, $locale);
            $flatTranslations = $this->translationManager->flattenTranslations($translations);
            
            return isset($flatTranslations[$key]) && !empty(trim($flatTranslations[$key]));
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère le pourcentage de completion des traductions pour une langue
     * 
     * @param string|null $locale Langue (défaut: langue courante)  
     * @param string $domain Domaine de traduction
     * @return float Pourcentage de completion (0-100)
     */
    public function getTranslationCompletion(?string $locale = null, string $domain = 'messages'): float
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        try {
            $stats = $this->translationManager->getTranslationStats($domain);
            
            foreach ($stats as $stat) {
                if ($stat['language']['code'] === $locale) {
                    return $stat['completion_percentage'];
                }
            }
            
            return 0.0;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    // --- Méthodes privées ---

    /**
     * Récupère la locale courante
     */
    private function getCurrentLocale(): string
    {
        if ($this->currentLocale !== null) {
            return $this->currentLocale;
        }

        $request = $this->requestStack->getCurrentRequest();
        
        if ($request) {
            $this->currentLocale = $request->getLocale();
        } else {
            // Fallback sur la langue par défaut
            $defaultLanguage = $this->getDefaultLanguage();
            $this->currentLocale = $defaultLanguage ? $defaultLanguage->getCode() : 'fr';
        }

        return $this->currentLocale;
    }

    /**
     * Remplace les paramètres dans une chaîne de traduction
     * 
     * Supporte plusieurs formats :
     * - %param% : Remplacement simple
     * - {param} : Remplacement avec accolades  
     * - {{param}} : Format Twig-like
     * - %count% -> %d formatage printf
     */
    private function replaceParameters(string $text, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            // Convertir la valeur en string
            $valueStr = is_scalar($value) ? (string) $value : json_encode($value);
            
            // Format %param%
            $text = str_replace("%{$key}%", $valueStr, $text);
            
            // Format {param}
            $text = str_replace("{{$key}}", $valueStr, $text);
            
            // Format {{param}}
            $text = str_replace("{{{$key}}}", $valueStr, $text);
            
            // Format :param
            $text = str_replace(":{$key}", $valueStr, $text);
        }
        
        // Support des formats printf pour les nombres
        if (preg_match_all('/%(\w+)%/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                if (isset($parameters[$match]) && is_numeric($parameters[$match])) {
                    $text = preg_replace("/%{$match}%/", '%d', $text);
                    $text = sprintf($text, $parameters[$match]);
                    break; // Un seul remplacement printf par appel
                }
            }
        }
        
        return $text;
    }

    /**
     * Nom de l'extension pour Twig
     */
    public function getName(): string
    {
        return 'oragon_translation';
    }
}
