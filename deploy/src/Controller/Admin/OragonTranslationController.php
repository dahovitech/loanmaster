<?php

namespace App\Controller\Admin;

use App\Repository\PageRepository;
use App\Repository\SeoRepository;
use App\Repository\BankRepository;
use App\Repository\NotificationRepository;
use App\Repository\FaqRepository;
use App\Repository\LoanTypeRepository;
use App\Repository\LanguageRepository;
use App\Repository\PageTranslationRepository;
use App\Repository\SeoTranslationRepository;
use App\Repository\BankTranslationRepository;
use App\Repository\NotificationTranslationRepository;
use App\Repository\FaqTranslationRepository;
use App\Repository\LoanTypeTranslationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/oragon-translations')]
#[IsGranted('ROLE_ADMIN')]
class OragonTranslationController extends AbstractController
{
    public function __construct(
        private PageRepository $pageRepository,
        private SeoRepository $seoRepository,
        private BankRepository $bankRepository,
        private NotificationRepository $notificationRepository,
        private FaqRepository $faqRepository,
        private LoanTypeRepository $loanTypeRepository,
        private LanguageRepository $languageRepository,
        private PageTranslationRepository $pageTranslationRepository,
        private SeoTranslationRepository $seoTranslationRepository,
        private BankTranslationRepository $bankTranslationRepository,
        private NotificationTranslationRepository $notificationTranslationRepository,
        private FaqTranslationRepository $faqTranslationRepository,
        private LoanTypeTranslationRepository $loanTypeTranslationRepository
    ) {}

    #[Route('/', name: 'admin_oragon_translations_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $languageCount = count($languages);
        
        // Entités et leurs statistiques
        $entities = [
            'pages' => [
                'title' => 'Pages',
                'icon' => 'bi-file-text',
                'repository' => $this->pageRepository,
                'translationRepository' => $this->pageTranslationRepository,
                'editRoute' => 'admin_page_translations_index',
                'color' => 'primary'
            ],
            'seo' => [
                'title' => 'SEO',
                'icon' => 'bi-search',
                'repository' => $this->seoRepository,
                'translationRepository' => $this->seoTranslationRepository,
                'editRoute' => 'admin_seo_translations_index',
                'color' => 'success'
            ],
            'banks' => [
                'title' => 'Banques',
                'icon' => 'bi-bank',
                'repository' => $this->bankRepository,
                'translationRepository' => $this->bankTranslationRepository,
                'editRoute' => 'admin_bank_translations_index',
                'color' => 'info'
            ],
            'notifications' => [
                'title' => 'Notifications',
                'icon' => 'bi-bell',
                'repository' => $this->notificationRepository,
                'translationRepository' => $this->notificationTranslationRepository,
                'editRoute' => 'admin_notification_translations_index',
                'color' => 'warning'
            ],
            'faqs' => [
                'title' => 'FAQ',
                'icon' => 'bi-question-circle',
                'repository' => $this->faqRepository,
                'translationRepository' => $this->faqTranslationRepository,
                'editRoute' => 'admin_faq_translations_index',
                'color' => 'secondary'
            ],
            'loanTypes' => [
                'title' => 'Types de prêts',
                'icon' => 'bi-card-list',
                'repository' => $this->loanTypeRepository,
                'translationRepository' => $this->loanTypeTranslationRepository,
                'editRoute' => 'admin_loan_type_translations_index',
                'color' => 'dark'
            ]
        ];

        $stats = [];
        $totalEntities = 0;
        $totalTranslations = 0;
        $totalPossible = 0;

        foreach ($entities as $key => $entity) {
            $entityCount = $entity['repository']->count([]);
            $translationCount = $entity['translationRepository']->count([]);
            $possibleTranslations = $entityCount * $languageCount;
            
            $stats[$key] = [
                'count' => $entityCount,
                'translations' => $translationCount,
                'possible' => $possibleTranslations,
                'percentage' => $possibleTranslations > 0 ? round(($translationCount / $possibleTranslations) * 100) : 0,
                'title' => $entity['title'],
                'icon' => $entity['icon'],
                'editRoute' => $entity['editRoute'],
                'color' => $entity['color']
            ];
            
            $totalEntities += $entityCount;
            $totalTranslations += $translationCount;
            $totalPossible += $possibleTranslations;
        }

        $globalStats = [
            'totalEntities' => $totalEntities,
            'totalTranslations' => $totalTranslations,
            'totalPossible' => $totalPossible,
            'globalPercentage' => $totalPossible > 0 ? round(($totalTranslations / $totalPossible) * 100) : 0,
            'languageCount' => $languageCount
        ];

        return $this->render('admin/oragon_translation/dashboard.html.twig', [
            'stats' => $stats,
            'globalStats' => $globalStats,
            'languages' => $languages
        ]);
    }
}
