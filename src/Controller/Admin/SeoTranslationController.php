<?php

namespace App\Controller\Admin;

use App\Entity\Seo;
use App\Entity\SeoTranslation;
use App\Entity\Language;
use App\Repository\SeoRepository;
use App\Repository\SeoTranslationRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/seo-translations')]
#[IsGranted('ROLE_ADMIN')]
class SeoTranslationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SeoRepository $seoRepository,
        private SeoTranslationRepository $seoTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/', name: 'admin_seo_translations_index', methods: ['GET'])]
    public function index(): Response
    {
        $seos = $this->seoRepository->findAll();
        $languages = $this->languageRepository->findActiveLanguages();
        
        $stats = [];
        foreach ($seos as $seo) {
            $translationCount = $this->seoTranslationRepository->count(['translatable' => $seo]);
            $stats[$seo->getId()] = [
                'total' => count($languages),
                'translated' => $translationCount,
                'percentage' => count($languages) > 0 ? round(($translationCount / count($languages)) * 100) : 0
            ];
        }

        return $this->render('admin/seo_translation/index.html.twig', [
            'seos' => $seos,
            'languages' => $languages,
            'stats' => $stats
        ]);
    }

    #[Route('/seo/{id}', name: 'admin_seo_translations_edit', methods: ['GET', 'POST'])]
    public function edit(Seo $seo, Request $request): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $translations = [];
        
        foreach ($languages as $language) {
            $translation = $this->seoTranslationRepository->findOneBy([
                'translatable' => $seo,
                'language' => $language
            ]);
            
            if (!$translation) {
                $translation = new SeoTranslation();
                $translation->setTranslatable($seo);
                $translation->setLanguage($language);
            }
            
            $translations[$language->getCode()] = $translation;
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            foreach ($languages as $language) {
                $translation = $translations[$language->getCode()];
                $langCode = $language->getCode();
                
                if (isset($data['metaTitle'][$langCode])) {
                    $translation->setMetaTitle($data['metaTitle'][$langCode]);
                }
                if (isset($data['metaDescription'][$langCode])) {
                    $translation->setMetaDescription($data['metaDescription'][$langCode]);
                }
                if (isset($data['metaKeywords'][$langCode])) {
                    $translation->setMetaKeywords($data['metaKeywords'][$langCode]);
                }
                if (isset($data['ogTitle'][$langCode])) {
                    $translation->setOgTitle($data['ogTitle'][$langCode]);
                }
                if (isset($data['ogDescription'][$langCode])) {
                    $translation->setOgDescription($data['ogDescription'][$langCode]);
                }
                
                $this->entityManager->persist($translation);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Les traductions SEO ont été sauvegardées avec succès.');
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }
            
            return $this->redirectToRoute('admin_seo_translations_edit', ['id' => $seo->getId()]);
        }

        return $this->render('admin/seo_translation/edit.html.twig', [
            'seo' => $seo,
            'languages' => $languages,
            'translations' => $translations
        ]);
    }

    #[Route('/export/{id}', name: 'admin_seo_translations_export', methods: ['GET'])]
    public function export(Seo $seo): Response
    {
        $translations = $this->seoTranslationRepository->findBy(['translatable' => $seo]);
        
        $data = [];
        foreach ($translations as $translation) {
            $data[$translation->getLanguage()->getCode()] = [
                'metaTitle' => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'metaKeywords' => $translation->getMetaKeywords(),
                'ogTitle' => $translation->getOgTitle(),
                'ogDescription' => $translation->getOgDescription()
            ];
        }
        
        $response = new Response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="seo_translations_' . $seo->getId() . '.json"');
        
        return $response;
    }
}
