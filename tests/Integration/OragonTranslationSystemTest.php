<?php

namespace App\Tests\Integration;

use App\Entity\Language;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Bank;
use App\Entity\BankTranslation;
use App\Entity\LoanType;
use App\Entity\LoanTypeTranslation;
use App\Repository\LanguageRepository;
use App\Repository\PageRepository;
use App\Repository\PageTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OragonTranslationSystemTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private LanguageRepository $languageRepository;
    private PageRepository $pageRepository;
    private PageTranslationRepository $pageTranslationRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
            
        $this->languageRepository = $this->entityManager->getRepository(Language::class);
        $this->pageRepository = $this->entityManager->getRepository(Page::class);
        $this->pageTranslationRepository = $this->entityManager->getRepository(PageTranslation::class);
    }

    public function testLanguageEntityCreation(): void
    {
        // Test création d'une langue
        $language = new Language();
        $language->setCode('test');
        $language->setName('Test Language');
        $language->setNativeName('Test Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(1);

        $this->entityManager->persist($language);
        $this->entityManager->flush();

        $this->assertNotNull($language->getId());
        $this->assertEquals('test', $language->getCode());
        $this->assertTrue($language->isActive());
    }

    public function testPageTranslationOragonPattern(): void
    {
        // Créer une langue de test
        $language = new Language();
        $language->setCode('test_page');
        $language->setName('Test Page Language');
        $language->setNativeName('Test Page Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(2);

        $this->entityManager->persist($language);

        // Créer une page
        $page = new Page();
        $page->setTitle('Test Page');
        $page->setSlug('test-page');
        $page->setContent('Test content');
        $page->setIsActive(true);

        $this->entityManager->persist($page);

        // Créer une traduction avec le pattern Oragon
        $translation = new PageTranslation();
        $translation->setTranslatable($page);
        $translation->setLanguage($language);
        $translation->setTitle('Page de test');
        $translation->setContent('Contenu de test');
        $translation->setMetaDescription('Description de test');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // Vérifications
        $this->assertNotNull($translation->getId());
        $this->assertEquals($page, $translation->getTranslatable());
        $this->assertEquals($language, $translation->getLanguage());
        $this->assertEquals('Page de test', $translation->getTitle());

        // Vérifier la relation inverse
        $this->assertTrue($page->getTranslations()->contains($translation));
    }

    public function testBankTranslationOragonPattern(): void
    {
        // Créer une langue de test
        $language = new Language();
        $language->setCode('test_bank');
        $language->setName('Test Bank Language');
        $language->setNativeName('Test Bank Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(3);

        $this->entityManager->persist($language);

        // Créer une banque
        $bank = new Bank();
        $bank->setLogo('logo.png');
        $bank->setUrl('https://example.com');
        $bank->setNotary('Notaire Test');
        $bank->setName('Banque Test');
        $bank->setManagerName('Manager Test');
        $bank->setSignBank('Signature Banque');
        $bank->setSignNotary('Signature Notaire');
        $bank->setAddress('Adresse Test');

        $this->entityManager->persist($bank);

        // Créer une traduction avec le pattern Oragon
        $translation = new BankTranslation();
        $translation->setTranslatable($bank);
        $translation->setLanguage($language);
        $translation->setName('Banque de Test');
        $translation->setAddress('Adresse de test');
        $translation->setSignBank('Signature de la banque');
        $translation->setSignNotary('Signature du notaire');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // Vérifications
        $this->assertNotNull($translation->getId());
        $this->assertEquals($bank, $translation->getTranslatable());
        $this->assertEquals($language, $translation->getLanguage());
        $this->assertEquals('Banque de Test', $translation->getName());

        // Vérifier la relation inverse
        $this->assertTrue($bank->getTranslations()->contains($translation));
    }

    public function testLoanTypeTranslationOragonPattern(): void
    {
        // Créer une langue de test
        $language = new Language();
        $language->setCode('test_loan');
        $language->setName('Test Loan Language');
        $language->setNativeName('Test Loan Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(4);

        $this->entityManager->persist($language);

        // Créer un type de prêt
        $loanType = new LoanType();
        $loanType->setCode('TEST_LOAN');
        $loanType->setName('Test Loan Type');
        $loanType->setDescription('Test loan description');
        $loanType->setIsActive(true);
        $loanType->setDefaultInterestRate('5.5');
        $loanType->setDefaultDurationMonths(12);
        $loanType->setSortOrder(1);

        $this->entityManager->persist($loanType);

        // Créer une traduction avec le pattern Oragon
        $translation = new LoanTypeTranslation();
        $translation->setTranslatable($loanType);
        $translation->setLanguage($language);
        $translation->setName('Type de Prêt Test');
        $translation->setDescription('Description du prêt de test');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // Vérifications
        $this->assertNotNull($translation->getId());
        $this->assertEquals($loanType, $translation->getTranslatable());
        $this->assertEquals($language, $translation->getLanguage());
        $this->assertEquals('Type de Prêt Test', $translation->getName());

        // Vérifier la relation inverse
        $this->assertTrue($loanType->getTranslations()->contains($translation));
    }

    public function testUniqueConstraintTranslationPerLanguage(): void
    {
        // Créer une langue de test
        $language = new Language();
        $language->setCode('test_unique');
        $language->setName('Test Unique Language');
        $language->setNativeName('Test Unique Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(5);

        $this->entityManager->persist($language);

        // Créer une page
        $page = new Page();
        $page->setTitle('Test Unique Page');
        $page->setSlug('test-unique-page');
        $page->setContent('Test unique content');
        $page->setIsActive(true);

        $this->entityManager->persist($page);

        // Créer la première traduction
        $translation1 = new PageTranslation();
        $translation1->setTranslatable($page);
        $translation1->setLanguage($language);
        $translation1->setTitle('Page unique 1');
        $translation1->setContent('Contenu unique 1');

        $this->entityManager->persist($translation1);
        $this->entityManager->flush();

        // Tenter de créer une seconde traduction pour la même page et langue
        $translation2 = new PageTranslation();
        $translation2->setTranslatable($page);
        $translation2->setLanguage($language);
        $translation2->setTitle('Page unique 2');
        $translation2->setContent('Contenu unique 2');

        $this->entityManager->persist($translation2);

        // Cette opération devrait échouer à cause de la contrainte unique
        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testRepositoryFindByTranslatableAndLanguage(): void
    {
        // Créer une langue de test
        $language = new Language();
        $language->setCode('test_repo');
        $language->setName('Test Repository Language');
        $language->setNativeName('Test Repository Native');
        $language->setIsActive(true);
        $language->setIsDefault(false);
        $language->setSortOrder(6);

        $this->entityManager->persist($language);

        // Créer une page
        $page = new Page();
        $page->setTitle('Test Repository Page');
        $page->setSlug('test-repository-page');
        $page->setContent('Test repository content');
        $page->setIsActive(true);

        $this->entityManager->persist($page);

        // Créer une traduction
        $translation = new PageTranslation();
        $translation->setTranslatable($page);
        $translation->setLanguage($language);
        $translation->setTitle('Page Repository Test');
        $translation->setContent('Contenu Repository Test');

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        // Test du repository
        $foundTranslation = $this->pageTranslationRepository->findOneBy([
            'translatable' => $page,
            'language' => $language
        ]);

        $this->assertNotNull($foundTranslation);
        $this->assertEquals('Page Repository Test', $foundTranslation->getTitle());
        $this->assertEquals($page, $foundTranslation->getTranslatable());
        $this->assertEquals($language, $foundTranslation->getLanguage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer la base de données après chaque test
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
