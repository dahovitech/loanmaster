<?php

namespace App\Validator;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for SecureFileUpload constraint
 */
class SecureFileUploadValidator extends ConstraintValidator
{
    private const SUSPICIOUS_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 
        'php', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp'
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SecureFileUpload) {
            throw new UnexpectedTypeException($constraint, SecureFileUpload::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            return;
        }

        // Check if file was uploaded successfully
        if (!$value->isValid()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Validate file size
        if ($value->getSize() > $constraint->maxSize) {
            $this->context->buildViolation($constraint->maxSizeMessage)
                ->setParameter('{{ size }}', $this->formatBytes($value->getSize()))
                ->setParameter('{{ max_size }}', $this->formatBytes($constraint->maxSize))
                ->addViolation();
            return;
        }

        // Validate MIME type
        $mimeType = $value->getMimeType();
        if (!in_array($mimeType, $constraint->allowedMimeTypes, true)) {
            $this->context->buildViolation($constraint->invalidTypeMessage)
                ->setParameter('{{ type }}', $mimeType)
                ->setParameter('{{ allowed_types }}', implode(', ', $constraint->allowedMimeTypes))
                ->addViolation();
            return;
        }

        // Check file extension security
        $originalName = $value->getClientOriginalName();
        if ($this->hasSuspiciousExtension($originalName)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Basic virus scanning (check for suspicious patterns)
        if ($constraint->scanForVirus && $this->containsSuspiciousContent($value)) {
            $this->context->buildViolation($constraint->virusMessage)
                ->addViolation();
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    private function hasSuspiciousExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::SUSPICIOUS_EXTENSIONS, true);
    }

    private function containsSuspiciousContent(UploadedFile $file): bool
    {
        // Basic suspicious content detection
        $suspiciousPatterns = [
            '/<%[\s\S]*?%>/',           // ASP/JSP tags
            '/<\?php[\s\S]*?\?>/',      // PHP tags
            '/<script[\s\S]*?<\/script>/i', // JavaScript
            '/eval\s*\(/i',             // eval function
            '/exec\s*\(/i',             // exec function
            '/system\s*\(/i',           // system function
        ];

        try {
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                return false;
            }

            // Only check first 1KB for performance
            $content = substr($content, 0, 1024);

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If we can't read the file, consider it suspicious
            return true;
        }

        return false;
    }
}