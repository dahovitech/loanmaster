<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;

class DefaultLanguageService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getDefaultLanguage(): ?Language
    {
        return $this->entityManager->getRepository(Language::class)->findOneBy(['isDefault' => true]);
    }

    public function getDefaultLanguageCode()
    {
        $language= $this->entityManager->getRepository(Language::class)->findOneBy(['isDefault' => true]);

        return $language->getCode();
    }

    public function isDefaultLanguage(Language $language): bool
    {
        return $language->isDefault();
    }
}