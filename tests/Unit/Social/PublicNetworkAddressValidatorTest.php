<?php

namespace Tests\Unit\Social;

use App\Support\Network\PublicNetworkAddressValidator;
use PHPUnit\Framework\TestCase;

class PublicNetworkAddressValidatorTest extends TestCase
{
    public function test_only_the_carrier_grade_nat_slice_of_the_public_100_range_is_blocked(): void
    {
        $validator = new PublicNetworkAddressValidator;

        $this->assertTrue($validator->isValid('100.63.255.255'));
        $this->assertFalse($validator->isValid('100.64.0.1'));
        $this->assertFalse($validator->isValid('100.127.255.254'));
        $this->assertTrue($validator->isValid('100.128.0.1'));
    }
}
