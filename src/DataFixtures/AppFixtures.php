<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Bank;
use App\Entity\User;
use App\Entity\Language;
use App\Entity\Setting;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    /**
     * @var UserPasswordHasherInterface
     */
    private $passwordHasher;

    /**
     * AppFixtures constructor.
     * @param UserPasswordHasherInterface $passwordHasher
     */
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $languageData = [
            [
                "name" => "Arabic",
                "code" => "ar",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "rtl"
            ],
            [
                "name" => "Chinese",
                "code" => "zh",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Danish",
                "code" => "da",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "German",
                "code" => "de",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "English",
                "code" => "en",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Spanish",
                "code" => "es",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "French",
                "code" => "fr",
                "isDefault" => true,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Hebrew",
                "code" => "he",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "rtl"
            ],
            [
                "name" => "Italian",
                "code" => "it",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Japanese",
                "code" => "ja",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Dutch",
                "code" => "nl",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Polish",
                "code" => "pl",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ],
            [
                "name" => "Portuguese",
                "code" => "pt",
                "isDefault" => false,
                "isEnabled" => true,
                "dir" => "ltr"
            ]
        ];
        
      
        foreach ($languageData as $key => $value) {
            $language = new Language();
            $language->setName($value["name"]);
            $language->setCode($value["code"]);
            $language->setDir($value["dir"]);
            $language->setIsDefault($value["isDefault"]); // or false, depending on your needs
            $language->setIsEnabled($value["isEnabled"]); // or false, depending on your needs
        
            // Persist the entity to the database
            $manager->persist($language);
        }



        $admin = new User();
        $admin->setRoles(["ROLE_ADMIN"]);
        $admin->setEmail("jprud67@gmail.com");
        $admin->setLocale("fr");
        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                "password"
            )
        );
        $manager->persist($admin);



        $super_user = new User();
        $super_user->setRoles(["ROLE_SUPER_ADMIN"]);
        $super_user->setLocale("fr");
        $super_user->setEmail("brakatabra@gmail.com");
        $super_user->setPassword(
            $this->passwordHasher->hashPassword(
                $super_user,
                "super_administrator1a4I1dYjhGqo8"
            )
        );
        $manager->persist($super_user);

        $setting = new Setting();
        $setting->setTitle("ORAGO-FINANCE");
        $setting->setEmail("contact@oragofinance.online");
        $setting->setEmailSender("noreply@oragofinance.online");
        $setting->setDevise("€");
        $manager->persist($setting);;

        $manager->flush();
    }
}