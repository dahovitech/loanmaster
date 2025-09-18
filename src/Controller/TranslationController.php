<?php

namespace App\Controller;

use App\Repository\LanguageRepository;
use App\Service\TranslationManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur public des traductions - Système Oragon
 * 
 * Fournit des API publiques pour :
 * - Récupération des traductions côté frontend
 * - Changement de langue
 * - Cache des traductions
 * 
 * @author Prudence ASSOGBA
 */
#[Route('/translations')]
class TranslationController extends AbstractController
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private TranslationManagerService $translationManager
    ) {}

    /**
     * API pour récupérer les traductions d'une langue et domaine spécifiques
     */
    #[Route('/api/{locale}/{domain}', name: 'translations_api_get', methods: ['GET'])]
    public function getTranslations(string $locale, string $domain): JsonResponse
    {
        try {
            $language = $this->languageRepository->findOneBy(['code' => $locale]);
            
            if (!$language || !$language->isIsEnabled()) {
                return $this->json([
                    'error' => 'Langue non supportée ou désactivée',
                    'locale' => $locale
                ], 404);
            }

            $translations = $this->translationManager->getTranslationsForDomain($locale, $domain);
            
            return $this->json([
                'locale' => $locale,
                'domain' => $domain,
                'translations' => $translations,
                'cached_at' => date('c')
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des traductions',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API pour récupérer toutes les traductions d'une langue
     */
    #[Route('/api/{locale}', name: 'translations_api_get_all', methods: ['GET'])]
    public function getAllTranslations(string $locale): JsonResponse
    {
        try {
            $language = $this->languageRepository->findOneBy(['code' => $locale]);
            
            if (!$language || !$language->isIsEnabled()) {
                return $this->json([
                    'error' => 'Langue non supportée ou désactivée',
                    'locale' => $locale
                ], 404);
            }

            $allTranslations = $this->translationManager->getAllTranslations($locale);
            
            return $this->json([
                'locale' => $locale,
                'translations' => $allTranslations,
                'domains' => array_keys($allTranslations),
                'cached_at' => date('c')
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des traductions',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API pour obtenir la liste des langues disponibles
     */
    #[Route('/api/languages', name: 'translations_api_languages', methods: ['GET'])]
    public function getAvailableLanguages(): JsonResponse
    {
        try {
            $languages = $this->languageRepository->findBy(['isEnabled' => true], ['sortOrder' => 'ASC']);
            
            $languageData = [];
            foreach ($languages as $language) {
                $languageData[] = [
                    'code' => $language->getCode(),
                    'name' => $language->getName(),
                    'nativeName' => $language->getNativeName(),
                    'dir' => $language->getDir(),
                    'isDefault' => $language->isIsDefault(),
                    'sortOrder' => $language->getSortOrder()
                ];
            }
            
            return $this->json([
                'languages' => $languageData,
                'count' => count($languageData),
                'cached_at' => date('c')
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des langues',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint pour changer la langue de l'interface
     */
    #[Route('/change-locale/{locale}', name: 'change_locale', methods: ['GET', 'POST'])]
    public function changeLocale(string $locale, Request $request): Response
    {
        try {
            $language = $this->languageRepository->findOneBy(['code' => $locale, 'isEnabled' => true]);
            
            if (!$language) {
                $this->addFlash('error', 'Langue non supportée : ' . $locale);
                return $this->redirectToRoute('app_default_index');
            }

            // Stocker la langue dans la session
            $request->getSession()->set('_locale', $locale);
            
            // Rediriger vers la page demandée ou la page d'accueil
            $referer = $request->headers->get('referer');
            if ($referer && $this->isLocalUrl($referer)) {
                return $this->redirect($referer);
            }
            
            return $this->redirectToRoute('app_default_index');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du changement de langue');
            return $this->redirectToRoute('app_default_index');
        }
    }

    /**
     * Page de test pour les traductions (dev uniquement)
     */
    #[Route('/test', name: 'translations_test', methods: ['GET'])]
    public function testTranslations(): Response
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        $languages = $this->languageRepository->findBy(['isEnabled' => true]);
        $domains = ['messages', 'forms', 'navigation', 'errors'];
        
        return $this->render('translations/test.html.twig', [
            'languages' => $languages,
            'domains' => $domains,
            'current_locale' => $this->getParameter('kernel.default_locale')
        ]);
    }

    /**
     * Vérifie si une URL est locale au site
     */
    private function isLocalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $requestHost = parse_url($this->getParameter('app.base_url') ?? '', PHP_URL_HOST);
        
        return $host === $requestHost || $host === null;
    }
}
