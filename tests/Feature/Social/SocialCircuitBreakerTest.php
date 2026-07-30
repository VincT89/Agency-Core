<?php

namespace Tests\Feature\Social;

use App\Services\SocialCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SocialCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_failures_are_isolated_by_provider_client_and_account(): void
    {
        $breaker = new SocialCircuitBreaker(
            failureThreshold: 2,
            resetTimeout: 60
        );
        $first = $breaker->scoped('facebook', 'client-1:account-1');
        $second = $breaker->scoped('facebook', 'client-2:account-2');
        $otherProvider = $breaker->scoped('tiktok', 'client-1:account-1');

        $first->recordFailure();
        $first->recordFailure();

        $this->assertSame(
            SocialCircuitBreaker::STATE_OPEN,
            $first->getState()
        );
        $this->assertSame(
            SocialCircuitBreaker::STATE_CLOSED,
            $second->getState()
        );
        $this->assertSame(
            SocialCircuitBreaker::STATE_CLOSED,
            $otherProvider->getState()
        );

        $second->recordSuccess();

        $this->assertSame(
            SocialCircuitBreaker::STATE_OPEN,
            $first->getState()
        );
    }
}
