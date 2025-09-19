<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpKernel\KernelInterface;

class TranslationManager
{
    private Filesystem $filesystem;

    public function __construct(
        private KernelInterface $kernel
    ) {
        $this->filesystem = new Filesystem();
    }

    public function createTranslationFileIfNotExists(string $locale, string $defaultLocale): void
    {
        $projectDir = $this->kernel->getProjectDir();
        $yamlFilePath = sprintf('%s/translations/messages.%s.yaml', $projectDir, $locale);
        $defaultYamlFilePath = sprintf('%s/translations/messages.%s.yaml', $projectDir, $defaultLocale);

        if (!$this->filesystem->exists($yamlFilePath)) {
            if ($this->filesystem->exists($defaultYamlFilePath)) {
                // Copier le fichier de traduction par défaut
                $this->filesystem->copy($defaultYamlFilePath, $yamlFilePath);
            } else {
                // Créer un fichier de traduction vide
                $this->filesystem->touch($yamlFilePath);
            }
        }
    }

    public function getTranslationFilePath(string $locale): string
    {
        $projectDir = $this->kernel->getProjectDir();
        return sprintf('%s/translations/messages.%s.yaml', $projectDir, $locale);
    }

    public function parseTranslationFile(string $yamlFilePath): array
    {
        if (!$this->filesystem->exists($yamlFilePath) || !is_readable($yamlFilePath)) {
            return [];
        }

        $parsedData = Yaml::parseFile($yamlFilePath);

        return is_array($parsedData) ? $parsedData : [];
    }

    public function updateTranslationFile(string $yamlFilePath, array $data): void
    {
        $yaml = Yaml::dump($data, 2, 2);
        file_put_contents($yamlFilePath, $yaml);
    }

    public function deleteTranslationFile(string $yamlFilePath): void
    {
        if ($this->filesystem->exists($yamlFilePath)) {
            $this->filesystem->remove($yamlFilePath);
        }
    }
}
