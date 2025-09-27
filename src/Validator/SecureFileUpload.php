<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint for validating secure file uploads
 */
#[\Attribute]
class SecureFileUpload extends Constraint
{
    public string $message = 'The uploaded file is not valid or secure.';
    public string $invalidTypeMessage = 'The file type "{{ type }}" is not allowed. Allowed types: {{ allowed_types }}.';
    public string $maxSizeMessage = 'The file is too large ({{ size }}). Maximum allowed size is {{ max_size }}.';
    public string $virusMessage = 'The uploaded file appears to contain malicious content.';
    
    public array $allowedMimeTypes = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    public int $maxSize = 5242880; // 5MB
    public bool $scanForVirus = true;

    public function __construct(
        array $allowedMimeTypes = null,
        int $maxSize = null,
        bool $scanForVirus = null,
        string $message = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->allowedMimeTypes = $allowedMimeTypes ?? $this->allowedMimeTypes;
        $this->maxSize = $maxSize ?? $this->maxSize;
        $this->scanForVirus = $scanForVirus ?? $this->scanForVirus;
        $this->message = $message ?? $this->message;
    }
}