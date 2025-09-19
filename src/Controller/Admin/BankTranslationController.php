<?php

namespace App\Controller\Admin;

use App\Entity\Bank;
use App\Entity\BankTranslation;
use App\Entity\Language;
use App\Repository\BankRepository;
use App\Repository\BankTranslationRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bank-translations')]
#[IsGranted('ROLE_ADMIN')]
class BankTranslationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BankRepository $bankRepository,
        private BankTranslationRepository $bankTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/', name: 'admin_bank_translations_index', methods: ['GET'])]
    public function index(): Response
    {
        $banks = $this->bankRepository->findAll();
        $languages = $this->languageRepository->findActiveLanguages();
        
        $stats = [];
        foreach ($banks as $bank) {
            $translationCount = $this->bankTranslationRepository->count(['translatable' => $bank]);
            $stats[$bank->getId()] = [
                'total' => count($languages),
                'translated' => $translationCount,
                'percentage' => count($languages) > 0 ? round(($translationCount / count($languages)) * 100) : 0
            ];
        }

        return $this->render('admin/bank_translation/index.html.twig', [
            'banks' => $banks,
            'languages' => $languages,
            'stats' => $stats
        ]);
    }

    #[Route('/bank/{id}', name: 'admin_bank_translations_edit', methods: ['GET', 'POST'])]
    public function edit(Bank $bank, Request $request): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $translations = [];
        
        foreach ($languages as $language) {
            $translation = $this->bankTranslationRepository->findOneBy([
                'translatable' => $bank,
                'language' => $language
            ]);
            
            if (!$translation) {
                $translation = new BankTranslation();
                $translation->setTranslatable($bank);
                $translation->setLanguage($language);
            }
            
            $translations[$language->getCode()] = $translation;
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            foreach ($languages as $language) {
                $translation = $translations[$language->getCode()];
                $langCode = $language->getCode();
                
                if (isset($data['name'][$langCode])) {
                    $translation->setName($data['name'][$langCode]);
                }
                if (isset($data['address'][$langCode])) {
                    $translation->setAddress($data['address'][$langCode]);
                }
                if (isset($data['signBank'][$langCode])) {
                    $translation->setSignBank($data['signBank'][$langCode]);
                }
                if (isset($data['signNotary'][$langCode])) {
                    $translation->setSignNotary($data['signNotary'][$langCode]);
                }
                
                $this->entityManager->persist($translation);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Les traductions de banque ont été sauvegardées avec succès.');
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }
            
            return $this->redirectToRoute('admin_bank_translations_edit', ['id' => $bank->getId()]);
        }

        return $this->render('admin/bank_translation/edit.html.twig', [
            'bank' => $bank,
            'languages' => $languages,
            'translations' => $translations
        ]);
    }
}
