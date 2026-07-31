<?php

namespace Tests\Unit\Social;

use App\Domain\Social\Services\PreflightResult;
use PHPUnit\Framework\TestCase;

class PreflightResultUserMessageTest extends TestCase
{
    public function test_technical_preflight_details_are_not_returned_to_the_interface(): void
    {
        $result = new PreflightResult(
            false,
            [
                'media_url_42' => [
                    'passed' => false,
                    'message' => 'SQLSTATE[HY000] internal-secret',
                ],
            ],
            ['SQLSTATE[HY000] internal-secret']
        );

        $this->assertSame(
            ['Uno dei file non è raggiungibile. Selezionalo di nuovo.'],
            $result->userFacingErrors()
        );
        $this->assertNotContains('SQLSTATE[HY000] internal-secret', $result->userFacingErrors());
    }
}
