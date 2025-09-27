<?php

namespace App\Tests\Unit\Service\Mail;

use App\Entity\Setting;
use App\Service\Mail\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailServiceTest extends TestCase
{
    private EmailService $service;
    private MailerInterface $mailer;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private EntityRepository $settingRepository;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->settingRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')->with(Setting::class)->willReturn($this->settingRepository);

        $this->service = new EmailService(
            $this->mailer,
            $this->entityManager,
            $this->logger
        );
    }

    public function testSendTemplatedEmailSuccess(): void
    {
        $this->mailer->expects($this->once())->method('send');
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->sendTemplatedEmail(
            'test@example.com',
            'Test Subject',
            'emails/test.html.twig',
            ['name' => 'John'],
            'sender@example.com'
        );

        $this->assertTrue($result);
    }

    public function testSendTemplatedEmailFailure(): void
    {
        $exception = $this->createMock(TransportExceptionInterface::class);
        $exception->method('getMessage')->willReturn('SMTP Error');

        $this->mailer->method('send')->willThrowException($exception);
        $this->logger->expects($this->once())->method('error');

        $result = $this->service->sendTemplatedEmail(
            'test@example.com',
            'Test Subject',
            'emails/test.html.twig'
        );

        $this->assertFalse($result);
    }

    public function testGetSettingWithCache(): void
    {
        $setting = new Setting();
        $setting->setEmail('test@loanmaster.com');
        $setting->setName('LoanMaster Test');

        $this->settingRepository->expects($this->once())
            ->method('findOneBy')
            ->with([])
            ->willReturn($setting);

        // First call
        $result1 = $this->service->getSetting();
        // Second call should use cache
        $result2 = $this->service->getSetting();

        $this->assertSame($setting, $result1);
        $this->assertSame($setting, $result2);
    }

    public function testClearSettingCache(): void
    {
        $setting = new Setting();
        $this->settingRepository->method('findOneBy')->willReturn($setting);

        // Get setting to populate cache
        $this->service->getSetting();
        
        // Clear cache
        $this->service->clearSettingCache();
        
        // Next call should hit the repository again
        $this->settingRepository->expects($this->exactly(2))->method('findOneBy');
        $this->service->getSetting();
    }

    public function testSendTemplatedEmailWithDefaultFromAddress(): void
    {
        $setting = new Setting();
        $setting->setEmail('noreply@loanmaster.test');
        $setting->setName('LoanMaster');

        $this->settingRepository->method('findOneBy')->willReturn($setting);

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function ($email) {
                $from = $email->getFrom();
                return count($from) === 1 && 
                       $from[0]->getAddress() === 'noreply@loanmaster.test' &&
                       $from[0]->getName() === 'LoanMaster';
            }));

        $this->service->sendTemplatedEmail(
            'test@example.com',
            'Test',
            'template.html.twig'
        );
    }
}