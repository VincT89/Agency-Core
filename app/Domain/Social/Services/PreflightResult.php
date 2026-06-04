<?php

namespace App\Domain\Social\Services;

class PreflightResult
{
    public function __construct(
        public bool $isPass,
        public array $checks = [],
        public array $errors = []
    ) {}

    public static function pass(array $checks): self
    {
        return new self(true, $checks, []);
    }

    public static function fail(array $checks, array $errors): self
    {
        return new self(false, $checks, $errors);
    }

    public function addCheck(string $name, bool $passed, ?string $message = null): void
    {
        $this->checks[$name] = [
            'passed' => $passed,
            'message' => $message,
        ];

        if (!$passed && $message) {
            $this->errors[] = $message;
            $this->isPass = false;
        }
    }
}
