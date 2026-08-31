<?php

declare(strict_types=1);

namespace App\Domain\Validation;

final class ValidationException extends \DomainException
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Validation failed');
    }
}
