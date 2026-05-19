<?php

namespace App\Domain\Social\DTOs;

use App\Enums\Social\AssignmentValidationStatus;

class AssignmentValidationResult
{
    public function __construct(
        public readonly AssignmentValidationStatus $status,
        public readonly ?string $message = null
    ) {}

    public static function allowed(): self
    {
        return new self(AssignmentValidationStatus::Allowed);
    }

    public static function warning(string $message): self
    {
        return new self(AssignmentValidationStatus::Warning, $message);
    }

    public static function blocked(string $message): self
    {
        return new self(AssignmentValidationStatus::Blocked, $message);
    }

    public function isAllowed(): bool
    {
        return $this->status === AssignmentValidationStatus::Allowed;
    }

    public function isWarning(): bool
    {
        return $this->status === AssignmentValidationStatus::Warning;
    }

    public function isBlocked(): bool
    {
        return $this->status === AssignmentValidationStatus::Blocked;
    }
}
