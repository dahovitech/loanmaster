<?php

namespace App\Service;

use App\Entity\Setting;
use App\Entity\Language;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Intl\Countries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Languages;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class Util
{
    private ?Setting $setting = null;

    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        private RequestStack $requestStack,
        private HttpClientInterface $httpClient
    ){
    }

    public function getDefaultCountry(): string
    {
        $ipAddress = $this->requestStack->getCurrentRequest()->getClientIp();
        $url = "http://ip-api.com/json/".$ipAddress;

        try {
            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            return $data['countryCode'] ?? '';
        } catch (\Throwable $th) {
            // log the exception
        }

        return '';
    }

    public function getSetting(): ?Setting
    {
        if (null === $this->setting) {
            $this->setting = $this->entityManager->getRepository(Setting::class)->findOneBy([]);
        }

        return $this->setting;
    }

    /**
     * @param string $from
     * @param string $subject
     * @param string $template
     * @param array $recipients
     * @param array $param
     * @throws TransportExceptionInterface
     */
    public function sender(string $from, string $subject, string $template, array $recipients = [], array $param = []): void
    {
        $sender = (new TemplatedEmail())
            ->from(new Address($from, $this->getSetting()?->getTitle() ?? ''))
            ->subject($subject)
            ->htmlTemplate($template)
            ->priority(Email::PRIORITY_HIGH)
            ->context($param);

        foreach ($recipients as $recipient) {
            $sender->addTo(new Address($recipient));
        }

        $this->mailer->send($sender);
    }

    public function getLocales(): array
    {
        $languages = $this->entityManager->getRepository(Language::class)->findByPublish();

        $languageArray = [];
        foreach ($languages as $language) {
            $languageArray[] =  $language->getCode();
        }

        return $languageArray;
    }

    public function getAllLanguages(): array
    {
        $languages = $this->entityManager->getRepository(Language::class)->findByPublish();

        $languageArray = [];
        foreach ($languages as $language) {
            $languageArray[] =  $language->toArray();
        }

        return $languageArray;
    }

    public function getLocalesName(): array
    {
        $languages = $this->entityManager->getRepository(Language::class)->findByPublish();

        $languageArray = [];
        foreach ($languages as $language) {
            $languageArray[Languages::getName($language->getCode())] =  $language->getCode();
        }

        return $languageArray;
    }

    public function getDefaultLanguage() : string {
        
        return $this->entityManager->getRepository(Language::class)->findOneBy(["isDefault" => true])->getCode();
    }

}
