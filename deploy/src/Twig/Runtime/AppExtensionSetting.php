<?php

namespace App\Twig\Runtime;

use App\Entity\Faq;
use App\Entity\Seo;
use App\Entity\Loan;
use App\Entity\Page;
use App\Entity\Post;
use App\Entity\Step;
use App\Entity\Brand;
use App\Entity\Theme;
use App\Service\Util;
use App\Entity\Slider;
use App\Entity\Social;
use App\Entity\Service;
use App\Entity\Setting;
use App\Entity\Testimonial;
use App\Entity\PostCategory;
use App\Entity\ServiceCategory;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppExtensionSetting implements RuntimeExtensionInterface
{


    /**
     * AppExtension constructor.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function onLocales()
    {
        return $this->util->getLocales();
    }

    public function onAllLanguages()
    {
        return $this->util->getAllLanguages();
    }

    public function onLanguageName($code)
    {
        return  Languages::getName($code);
    }

    public function onCountryName($code)
    {
        return  Countries::getName($code);
    }


    public function onSetting($property, $isBoolean = false)
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $setting = $settingRepository->findOneBy([]); // Assuming you want to get the first setting
     
        if (!$setting) {
            $setting = new Setting();
            $this->entityManager->persist($setting);
            $this->entityManager->flush();
            $setting = $settingRepository->findOneBy([]);
        }
        $method = $isBoolean ? 'is' . ucfirst($property) : 'get' . ucfirst($property);
    
        if (!method_exists($setting, $method)) {
            throw new \BadMethodCallException(sprintf('Method "%s" does not exist in class "%s".', $method, get_class($setting)));
        }
    
        return $setting->$method()?$setting->$method():"";
    }
    
    public function onSeo($property, $isBoolean = false)
    {
        $seoRepository = $this->entityManager->getRepository(Seo::class);
        $seo = $seoRepository->findOneBy([]); // Assuming you want to get the first setting
    
        if (!$seo) {
            $seo = new Seo();
            $this->entityManager->persist($seo);
            $this->entityManager->flush();
            $seo = $seoRepository->findOneBy([]);

        }
    
        $method = $isBoolean ? 'is' . ucfirst($property) : 'get' . ucfirst($property);
    
        if (!method_exists($seo, $method)) {
            throw new \BadMethodCallException(sprintf('Method "%s" does not exist in class "%s".', $method, get_class($seo)));
        }
    
        return $seo->$method()?$seo->$method():"";
    }
    

    public function onSliders()
    {
        // dd($this->entityManager->getRepository(Slider::class)->findByPublish());
        return $this->entityManager->getRepository(Slider::class)->findByPublish();
    }


    public function onServices()
    {
        return $this->entityManager->getRepository(Service::class)->findByPublish();
    }

    public function onServiceCategories()
    {
        return $this->entityManager->getRepository(ServiceCategory::class)->findByPublish();
    }

    public function onPosts()
    {
        return $this->entityManager->getRepository(Post::class)->findByPublish();
    }

    public function onPostCategories()
    {
        return $this->entityManager->getRepository(PostCategory::class)->findByPublish();
    }

    public function onSteps()
    {
        return $this->entityManager->getRepository(Step::class)->findByPublish();
    }

    public function onBrands()
    {
        return $this->entityManager->getRepository(Brand::class)->findByPublish();
    }

    public function onPages()
    {
        return $this->entityManager->getRepository(Page::class)->findByPublish();
    }

    public function onSocials()
    {
        return $this->entityManager->getRepository(Social::class)->findByPublish();
    }

    public function onTestimonials()
    {
        return $this->entityManager->getRepository(Testimonial::class)->findByPublish();
    }

    public function onFaqs()
    {
        return $this->entityManager->getRepository(Faq::class)->findByPublish();
    }

    public function onLoans()
    {
        return $this->entityManager->getRepository(Loan::class)->findAll();
    }

    public function onLoan($user)
    {
        return $this->entityManager->getRepository(Loan::class)->findBy(["user"=>$user]);
    }

    public function onEntityField($field, $entityName, $entityId)
    {
        $entityClass = "App\\Entity\\" . $entityName;
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        $method = "get" . ucfirst($field);
        return $entity->$method();
    }

    public function onDefaultLanguage()
    {
        return $this->util->getDefaultLanguage();
    }

    public function onTheme($property)
    {
        $slug = $this->onSetting("theme");
        $theme = $this->entityManager->getRepository(Theme::class)->findOneBy(["slug" => $slug]);
        $method = 'get' . ucfirst($property);

        if (!method_exists($theme, $method)) {
            throw new \Exception(sprintf('Method "%s" does not exist in class "%s".', $method, get_class($theme)));
        }

        return $theme->$method();
    }
    public function onLanguageSwitcher()
    {
        $items = [];
        foreach ($this->onLocales() as $locale) {
            $items[] = [
                'locale' => $locale,
                'url' => $this->urlGenerator->generate('home', ['_locale' => $locale]),
            ];
        }
        return $items;
    }

    public function onLoanPrice($loanNumber, $type)
    {
        $price = null;
        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if ($type == "folder") {
            $price = $loan->getPrice();
        }
        if ($type == "contract") {
            $price = $loan->getPriceContract();
        }




        return $price;
    }
}
