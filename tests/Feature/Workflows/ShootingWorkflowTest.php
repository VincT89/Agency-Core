<?php

namespace Tests\Feature\Workflows;

use Tests\TestCase;

class ShootingWorkflowTest extends TestCase
{
    public function test_shooting_full_e2e_workflow(): void
    {
        $this->markTestIncomplete('TODO: implement full shooting workflow');
        
        // 1. creazione richiesta shooting
        // 2. assegnazione fotografo
        // 3. fotografo accetta/rifiuta
        // 4. cliente conferma
        // 5. policy: admin vede tutto
        // 6. policy: fotografo vede solo i propri
        // 7. policy: social vede solo quelli autorizzati
        // 8. redirect legacy /shoots
        // 9. collegamento con campagna marketing
    }
}
