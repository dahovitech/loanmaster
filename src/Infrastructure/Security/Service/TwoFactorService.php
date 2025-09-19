<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Service;

use App\Domain\Entity\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

final readonly class TwoFactorService
{
    public function __construct(
        private GoogleAuthenticatorInterface $googleAuthenticator,
        private TotpAuthenticatorInterface $totpAuthenticator
    ) {}

    public function enableTwoFactor(User $user, string $method = 'google'): array
    {
        if ($method === 'google') {
            return $this->enableGoogleAuthenticator($user);
        }
        
        if ($method === 'totp') {
            return $this->enableTotpAuthenticator($user);
        }
        
        throw new \InvalidArgumentException('Unsupported 2FA method: ' . $method);
    }
    
    private function enableGoogleAuthenticator(User $user): array
    {
        // Générer un secret
        $secret = $this->googleAuthenticator->generateSecret();
        $user->setGoogleAuthenticatorSecret($secret);
        
        // Générer le QR code
        $qrCodeContent = $this->googleAuthenticator->getQRContent($user);
        $qrCode = $this->generateQrCode($qrCodeContent);
        
        // Générer des codes de secours
        $backupCodes = $this->generateBackupCodes();
        $user->setBackupCodes($backupCodes);
        
        return [
            'secret' => $secret,
            'qrCode' => $qrCode,
            'backupCodes' => $backupCodes,
            'method' => 'google'
        ];
    }
    
    private function enableTotpAuthenticator(User $user): array
    {
        // Générer un secret TOTP
        $secret = $this->totpAuthenticator->generateSecret();
        $user->setTotpSecret($secret);
        
        // Générer le QR code
        $qrCodeContent = $this->totpAuthenticator->getQRContent($user);
        $qrCode = $this->generateQrCode($qrCodeContent);
        
        // Générer des codes de secours
        $backupCodes = $this->generateBackupCodes();
        $user->setBackupCodes($backupCodes);
        
        return [
            'secret' => $secret,
            'qrCode' => $qrCode,
            'backupCodes' => $backupCodes,
            'method' => 'totp'
        ];
    }
    
    public function disableTwoFactor(User $user): void
    {
        $user->setGoogleAuthenticatorSecret(null);
        $user->setTotpSecret(null);
        $user->setBackupCodes([]);
        $user->setTwoFactorEnabled(false);
    }
    
    public function verifyCode(User $user, string $code): bool
    {
        // Vérifier avec Google Authenticator
        if ($user->getGoogleAuthenticatorSecret()) {
            if ($this->googleAuthenticator->checkCode($user, $code)) {
                return true;
            }
        }
        
        // Vérifier avec TOTP
        if ($user->getTotpSecret()) {
            if ($this->totpAuthenticator->checkCode($user, $code)) {
                return true;
            }
        }
        
        // Vérifier les codes de secours
        return $this->verifyBackupCode($user, $code);
    }
    
    private function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        
        foreach ($backupCodes as $index => $backupCode) {
            if (hash_equals($backupCode, $code)) {
                // Supprimer le code utilisé
                unset($backupCodes[$index]);
                $user->setBackupCodes(array_values($backupCodes));
                return true;
            }
        }
        
        return false;
    }
    
    private function generateBackupCodes(): array
    {
        $codes = [];
        
        for ($i = 0; $i < 10; $i++) {
            $codes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
        }
        
        return $codes;
    }
    
    private function generateQrCode(string $content): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($content)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->build();
        
        return $result->getDataUri();
    }
    
    public function isTwoFactorRequired(User $user): bool
    {
        // 2FA obligatoire pour les admins
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return (bool) ($_ENV['TWO_FACTOR_AUTH_REQUIRED_FOR_ADMIN'] ?? true);
        }
        
        // 2FA obligatoire pour les loan officers
        if (in_array('ROLE_LOAN_OFFICER', $user->getRoles())) {
            return true;
        }
        
        return false;
    }
}
