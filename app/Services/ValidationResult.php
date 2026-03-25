<?php

namespace App\Services;

class ValidationResult
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public int $rowNumber,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }
}
