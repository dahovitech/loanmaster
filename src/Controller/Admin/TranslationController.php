<?php

namespace App\Controller\Admin;

use App\Repository\LanguageRepository;
use App\Service\TranslationManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur d'administration des traductions - Système Oragon
 * 
 * Interface complète de gestion des traductions avec :
 * - Éditeur visuel par domaine et langue
 * - Synchronisation automatique
 * - Statistiques temps réel
 * - Export/Import YAML
 * - Validation des traductions
 * 
 * @author Prudence ASSOGBA
 */
#[Route('/admin/translations')]
#[IsGranted('ROLE_ADMIN')]
class TranslationController extends AbstractController
{
    public function __construct(
        private TranslationManagerService $translationManager,
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Page principale de gestion des traductions
     */
    #[Route('/', name: 'admin_translation_index', methods: ['GET'])]
    public function index(): Response
    {
        $domains = $this->translationManager->getAvailableDomains();
        $languages = $this->languageRepository->findBy(['isEnabled' => true], ['code' => 'ASC']);
        $stats = $this->translationManager->getTranslationStats();
        
        return $this->render('admin/translation/index.html.twig', [
            'domains' => $domains,
            'languages' => $languages,
            'stats' => $stats,
            'page_title' => 'Gestion des traductions'
        ]);
    }

    /**
     * Éditeur de traductions pour un domaine et une langue
     */
    #[Route('/edit/{domain}/{locale}', name: 'admin_translation_edit', methods: ['GET'])]
    public function edit(string $domain, string $locale): Response
    {
        $language = $this->languageRepository->findOneBy(['code' => $locale, 'isEnabled' => true]);
        
        if (!$language) {
            $this->addFlash('error', "Langue '{$locale}' non trouvée ou désactivée");
            return $this->redirectToRoute('admin_translation_index');
        }

        $translations = $this->translationManager->getTranslations($domain, $locale);
        $flatTranslations = $this->translationManager->flattenTranslations($translations);
        
        // Récupérer les traductions de référence (langue par défaut)
        $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
        $referenceTranslations = [];
        
        if ($defaultLanguage && $defaultLanguage->getCode() !== $locale) {
            $referenceTranslations = $this->translationManager->getTranslations($domain, $defaultLanguage->getCode());
            $referenceTranslations = $this->translationManager->flattenTranslations($referenceTranslations);
        }
        
        return $this->render('admin/translation/edit.html.twig', [
            'domain' => $domain,
            'language' => $language,
            'translations' => $flatTranslations,
            'reference_translations' => $referenceTranslations,
            'page_title' => "Traductions {$domain} - {$language->getName()}"
        ]);
    }

    /**
     * Mise à jour des traductions via AJAX
     */
    #[Route('/update/{domain}/{locale}', name: 'admin_translation_update', methods: ['POST'])]
    public function update(Request $request, string $domain, string $locale): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!is_array($data)) {
                throw new \InvalidArgumentException('Données invalides');
            }
            
            // Validation des traductions
            $errors = $this->translationManager->validateTranslations($data);
            
            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errors
                ], 400);
            }
            
            // Reconstitution de la structure et sauvegarde
            $structuredTranslations = $this->translationManager->unflattenTranslations($data);
            $success = $this->translationManager->saveTranslations($domain, $locale, $structuredTranslations);
            
            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Traductions mises à jour avec succès'
                ]);
            } else {
                throw new \RuntimeException('Erreur lors de la sauvegarde');
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Synchronisation des traductions avec les langues actives
     */
    #[Route('/synchronize/{domain}', name: 'admin_translation_synchronize', methods: ['POST'])]
    public function synchronize(string $domain): JsonResponse
    {
        try {
            $this->translationManager->synchronizeWithLanguages($domain);
            
            return $this->json([
                'success' => true,
                'message' => "Synchronisation du domaine '{$domain}' terminée"
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API pour récupérer les statistiques via AJAX
     */
    #[Route('/stats/{domain}', name: 'admin_translation_stats', methods: ['GET'])]
    public function stats(string $domain = 'messages'): JsonResponse
    {
        $stats = $this->translationManager->getTranslationStats($domain);
        
        return $this->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Export des traductions au format YAML
     */
    #[Route('/export/{domain}/{locale}', name: 'admin_translation_export', methods: ['GET'])]
    public function export(string $domain, string $locale): Response
    {
        $language = $this->languageRepository->findOneBy(['code' => $locale]);
        
        if (!$language) {
            throw $this->createNotFoundException("Langue '{$locale}' non trouvée");
        }
        
        $yamlContent = $this->translationManager->exportTranslations($domain, $locale);
        
        $response = new Response($yamlContent);
        $response->headers->set('Content-Type', 'application/x-yaml');
        $response->headers->set('Content-Disposition', 
            "attachment; filename=\"{$domain}.{$locale}.yaml\"");
        
        return $response;
    }

    /**
     * Import des traductions depuis un fichier YAML
     */
    #[Route('/import/{domain}/{locale}', name: 'admin_translation_import', methods: ['POST'])]
    public function import(Request $request, string $domain, string $locale): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('translation_file');
            
            if (!$uploadedFile) {
                throw new \InvalidArgumentException('Aucun fichier fourni');
            }
            
            if ($uploadedFile->getClientOriginalExtension() !== 'yaml' && 
                $uploadedFile->getClientOriginalExtension() !== 'yml') {
                throw new \InvalidArgumentException('Le fichier doit être au format YAML');
            }
            
            $yamlContent = file_get_contents($uploadedFile->getPathname());
            $success = $this->translationManager->importTranslations($domain, $locale, $yamlContent);
            
            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Traductions importées avec succès'
                ]);
            } else {
                throw new \RuntimeException('Erreur lors de l\'import');
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Création d'un nouveau domaine de traduction
     */
    #[Route('/create-domain', name: 'admin_translation_create_domain', methods: ['POST'])]
    public function createDomain(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $domainName = $data['domain'] ?? '';
            
            if (empty($domainName)) {
                throw new \InvalidArgumentException('Nom du domaine requis');
            }
            
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $domainName)) {
                throw new \InvalidArgumentException('Nom de domaine invalide (lettres minuscules, chiffres et _ seulement)');
            }
            
            // Créer le domaine pour toutes les langues actives
            $this->translationManager->synchronizeWithLanguages($domainName);
            
            return $this->json([
                'success' => true,
                'message' => "Domaine '{$domainName}' créé avec succès"
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Recherche dans les traductions
     */
    #[Route('/search', name: 'admin_translation_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $query = $data['query'] ?? '';
            $domain = $data['domain'] ?? 'messages';
            $locale = $data['locale'] ?? 'fr';
            
            if (empty($query)) {
                return $this->json(['success' => true, 'results' => []]);
            }
            
            $translations = $this->translationManager->getTranslations($domain, $locale);
            $flatTranslations = $this->translationManager->flattenTranslations($translations);
            
            $results = [];
            
            foreach ($flatTranslations as $key => $value) {
                if (stripos($key, $query) !== false || stripos($value, $query) !== false) {
                    $results[] = [
                        'key' => $key,
                        'value' => $value,
                        'match_type' => stripos($key, $query) !== false ? 'key' : 'value'
                    ];
                }
            }
            
            return $this->json([
                'success' => true,
                'results' => array_slice($results, 0, 50) // Limiter à 50 résultats
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
