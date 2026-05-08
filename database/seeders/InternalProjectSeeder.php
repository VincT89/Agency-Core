<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class InternalProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::firstOrCreate(
            ['slug' => 'progetto-interno'],
            [
                'client_id'   => null,
                'name'        => 'Interno',
                'code'        => 'INT',
                'status'      => 'active',
                'description' => 'Progetto interno per attività operative, organizzative e task non legati a nessun cliente specifico.',
            ]
        );
    }
}
