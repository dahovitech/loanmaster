<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Language;
use App\Form\PageTranslationType;
use App\Repository\PageRepository;
use App\Repository\PageTranslationRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/page-translations')]
#[IsGranted('ROLE_ADMIN')]
class PageTranslationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageRepository $pageRepository,
        private PageTranslationRepository $pageTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/', name: 'admin_page_translations_index', methods: ['GET'])]
    public function index(): Response
    {
        $pages = $this->pageRepository->findAll();
        $languages = $this->languageRepository->findActiveLanguages();
        
        // Statistiques de traduction
        $stats = [];
        foreach ($pages as $page) {
            $translationCount = $this->pageTranslationRepository->count(['translatable' => $page]);
            $stats[$page->getId()] = [
                'total' => count($languages),
                'translated' => $translationCount,
                'percentage' => count($languages) > 0 ? round(($translationCount / count($languages)) * 100) : 0
            ];
        }

        return $this->render('admin/page_translation/index.html.twig', [
            'pages' => $pages,
            'languages' => $languages,
            'stats' => $stats
        ]);
    }

    #[Route('/page/{id}', name: 'admin_page_translations_edit', methods: ['GET', 'POST'])]
    public function edit(Page $page, Request $request): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $translations = [];
        
        // Récupérer ou créer les traductions pour chaque langue
        foreach ($languages as $language) {
            $translation = $this->pageTranslationRepository->findOneBy([
                'translatable' => $page,
                'language' => $language
            ]);
            
            if (!$translation) {
                $translation = new PageTranslation();
                $translation->setTranslatable($page);
                $translation->setLanguage($language);
            }
            
            $translations[$language->getCode()] = $translation;
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            foreach ($languages as $language) {
                $translation = $translations[$language->getCode()];
                $langCode = $language->getCode();
                
                if (isset($data['title'][$langCode])) {
                    $translation->setTitle($data['title'][$langCode]);
                }
                if (isset($data['content'][$langCode])) {
                    $translation->setContent($data['content'][$langCode]);
                }
                if (isset($data['metaDescription'][$langCode])) {
                    $translation->setMetaDescription($data['metaDescription'][$langCode]);
                }
                
                $this->entityManager->persist($translation);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Les traductions ont été sauvegardées avec succès.');
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }
            
            return $this->redirectToRoute('admin_page_translations_edit', ['id' => $page->getId()]);
        }

        return $this->render('admin/page_translation/edit.html.twig', [
            'page' => $page,
            'languages' => $languages,
            'translations' => $translations
        ]);
    }

    #[Route('/bulk-action', name: 'admin_page_translations_bulk', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $pageIds = $request->request->get('pages', []);
        
        if (!in_array($action, ['delete_empty', 'copy_from_default']) || empty($pageIds)) {
            return new JsonResponse(['error' => 'Action invalide'], 400);
        }
        
        $processed = 0;
        
        switch ($action) {
            case 'delete_empty':
                $processed = $this->deleteEmptyTranslations($pageIds);
                break;
                
            case 'copy_from_default':
                $processed = $this->copyFromDefaultLanguage($pageIds);
                break;
        }
        
        return new JsonResponse([
            'success' => true,
            'processed' => $processed,
            'message' => "Action exécutée sur {$processed} éléments."
        ]);
    }

    #[Route('/export/{id}', name: 'admin_page_translations_export', methods: ['GET'])]
    public function export(Page $page): Response
    {
        $translations = $this->pageTranslationRepository->findBy(['translatable' => $page]);
        
        $data = [];
        foreach ($translations as $translation) {
            $data[$translation->getLanguage()->getCode()] = [
                'title' => $translation->getTitle(),
                'content' => $translation->getContent(),
                'metaDescription' => $translation->getMetaDescription()
            ];
        }
        
        $response = new Response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="page_translations_' . $page->getSlug() . '.json"');
        
        return $response;
    }

    private function deleteEmptyTranslations(array $pageIds): int
    {
        $count = 0;
        
        foreach ($pageIds as $pageId) {
            $translations = $this->pageTranslationRepository->findBy(['translatable' => $pageId]);
            
            foreach ($translations as $translation) {
                if (empty($translation->getTitle()) && 
                    empty($translation->getContent()) && 
                    empty($translation->getMetaDescription())) {
                    
                    $this->entityManager->remove($translation);
                    $count++;
                }
            }
        }
        
        $this->entityManager->flush();
        return $count;
    }

    private function copyFromDefaultLanguage(array $pageIds): int
    {
        $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
        if (!$defaultLanguage) {
            return 0;
        }
        
        $count = 0;
        $languages = $this->languageRepository->findActiveLanguages();
        
        foreach ($pageIds as $pageId) {
            $page = $this->pageRepository->find($pageId);
            if (!$page) continue;
            
            $defaultTranslation = $this->pageTranslationRepository->findOneBy([
                'translatable' => $page,
                'language' => $defaultLanguage
            ]);
            
            if (!$defaultTranslation) continue;
            
            foreach ($languages as $language) {
                if ($language === $defaultLanguage) continue;
                
                $translation = $this->pageTranslationRepository->findOneBy([
                    'translatable' => $page,
                    'language' => $language
                ]);
                
                if (!$translation) {
                    $translation = new PageTranslation();
                    $translation->setTranslatable($page);
                    $translation->setLanguage($language);
                }
                
                // Copier uniquement si les champs sont vides
                if (empty($translation->getTitle())) {
                    $translation->setTitle($defaultTranslation->getTitle());
                }
                if (empty($translation->getContent())) {
                    $translation->setContent($defaultTranslation->getContent());
                }
                if (empty($translation->getMetaDescription())) {
                    $translation->setMetaDescription($defaultTranslation->getMetaDescription());
                }
                
                $this->entityManager->persist($translation);
                $count++;
            }
        }
        
        $this->entityManager->flush();
        return $count;
    }
}
