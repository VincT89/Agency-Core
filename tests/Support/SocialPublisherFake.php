<?php

namespace Tests\Support;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

class SocialPublisherFake
{
    private bool $called = false;
    private ?array $lastCallArgs = null;

    public function publish(array $data): array
    {
        $this->called = true;
        $this->lastCallArgs = $data;

        return ['external_id' => 'fake_external_id_123', 'status' => 'success'];
    }

    public function assertNotCalled(): void
    {
        PHPUnit::assertFalse($this->called, 'The publisher was unexpectedly called.');
    }

    public function assertCalled(): void
    {
        PHPUnit::assertTrue($this->called, 'The publisher was expected to be called but was not.');
    }
}
