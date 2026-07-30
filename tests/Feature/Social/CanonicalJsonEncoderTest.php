<?php

namespace Tests\Feature\Social;

use App\Domain\Social\Services\CanonicalJsonEncoder;
use Tests\TestCase;

class CanonicalJsonEncoderTest extends TestCase
{
    public function test_it_encodes_arrays_consistently(): void
    {
        $data1 = ['b' => 2, 'a' => 1];
        $data2 = ['a' => 1, 'b' => 2];
        $encoder = new CanonicalJsonEncoder();

        $this->assertEquals(
            $encoder->encode($data1),
            $encoder->encode($data2)
        );
    }
}
