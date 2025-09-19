<?php

namespace App\Controller\Admin;

use App\Entity\LoanType;
use App\Entity\LoanTypeTranslation;
use App\Entity\Language;
use App\Repository\LoanTypeRepository;
use App\Repository\LoanTypeTranslationRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/loan-type-translations')]
#[IsGranted('ROLE_ADMIN')]
class LoanTypeTranslationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanTypeRepository $loanTypeRepository,
        private LoanTypeTranslationRepository $loanTypeTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/', name: 'admin_loan_type_translations_index', methods: ['GET'])]
    public function index(): Response
    {
        $loanTypes = $this->loanTypeRepository->findAll();
        $languages = $this->languageRepository->findActiveLanguages();
        
        $stats = [];
        foreach ($loanTypes as $loanType) {
            $translationCount = $this->loanTypeTranslationRepository->count(['translatable' => $loanType]);
            $stats[$loanType->getId()] = [
                'total' => count($languages),
                'translated' => $translationCount,
                'percentage' => count($languages) > 0 ? round(($translationCount / count($languages)) * 100) : 0
            ];
        }

        return $this->render('admin/loan_type_translation/index.html.twig', [
            'loanTypes' => $loanTypes,
            'languages' => $languages,
            'stats' => $stats
        ]);
    }

    #[Route('/loan-type/{id}', name: 'admin_loan_type_translations_edit', methods: ['GET', 'POST'])]
    public function edit(LoanType $loanType, Request $request): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $translations = [];
        
        foreach ($languages as $language) {
            $translation = $this->loanTypeTranslationRepository->findOneBy([
                'translatable' => $loanType,
                'language' => $language
            ]);
            
            if (!$translation) {
                $translation = new LoanTypeTranslation();
                $translation->setTranslatable($loanType);
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
                if (isset($data['description'][$langCode])) {
                    $translation->setDescription($data['description'][$langCode]);
                }
                
                $this->entityManager->persist($translation);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Les traductions des types de prêts ont été sauvegardées avec succès.');
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success']);
            }
            
            return $this->redirectToRoute('admin_loan_type_translations_edit', ['id' => $loanType->getId()]);
        }

        return $this->render('admin/loan_type_translation/edit.html.twig', [
            'loanType' => $loanType,
            'languages' => $languages,
            'translations' => $translations
        ]);
    }
}
