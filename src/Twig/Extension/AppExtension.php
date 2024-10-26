<?php

namespace App\Twig\Extension;

use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use App\Twig\Runtime\AppExtensionNotif;
use App\Twig\Runtime\AppExtensionRuntime;
use App\Twig\Runtime\AppExtensionSetting;
use Doctrine\Persistence\ManagerRegistry;

class AppExtension extends AbstractExtension
{



    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('filter_name', [AppExtensionRuntime::class, 'doSomething']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [AppExtensionSetting::class, 'onSetting']),
            new TwigFunction('seo', [AppExtensionSetting::class, 'onSeo']),
            new TwigFunction('ArrayLocales', [AppExtensionSetting::class, 'onLocales']),
            new TwigFunction('all_languages', [AppExtensionSetting::class, 'onAllLanguages']),
            new TwigFunction('language_name', [AppExtensionSetting::class, 'onLanguageName']),
            new TwigFunction('country_name', [AppExtensionSetting::class, 'onCountryName']),
            new TwigFunction('loans', [AppExtensionSetting::class, 'onLoans']),
            new TwigFunction('loan', [AppExtensionSetting::class, 'onLoan']),
            new TwigFunction('sliders', [AppExtensionSetting::class, 'onSliders']),
            new TwigFunction('services', [AppExtensionSetting::class, 'onServices']),
            new TwigFunction('serviceCategories', [AppExtensionSetting::class, 'onServiceCategories']),
            new TwigFunction('posts', [AppExtensionSetting::class, 'onPosts']),
            new TwigFunction('postCategories', [AppExtensionSetting::class, 'onPostCategories']),
            new TwigFunction('steps', [AppExtensionSetting::class, 'onSteps']),
            new TwigFunction('brands', [AppExtensionSetting::class, 'onBrands']),
            new TwigFunction('pages', [AppExtensionSetting::class, 'onPages']),
            new TwigFunction('socials', [AppExtensionSetting::class, 'onSocials']),
            new TwigFunction('testimonials', [AppExtensionSetting::class, 'onTestimonials']),
            new TwigFunction('faqs', [AppExtensionSetting::class, 'onFaqs']),
            new TwigFunction('entity_field', [AppExtensionSetting::class, 'onEntityField']),
            new TwigFunction('notif_count', [AppExtensionNotif::class, 'onNotifCount']),
            new TwigFunction('theme', [AppExtensionSetting::class, 'onTheme']),
            new TwigFunction('language_switcher', [AppExtensionSetting::class, 'onLanguageSwitcher']),
            new TwigFunction('default_language', [AppExtensionSetting::class, 'onDefaultLanguage']),
            new TwigFunction('loan_price', [AppExtensionSetting::class, 'onLoanPrice']),
        ];
    }
}
