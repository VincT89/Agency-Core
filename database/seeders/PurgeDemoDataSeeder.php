<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PurgeDemoDataSeeder extends Seeder
{
    /**
     * The users table is handled separately so that exactly one administrator
     * is retained. Migrations are structural metadata, not application data.
     *
     * @var list<string>
     */
    private const PRESERVED_TABLES = [
        'migrations',
        'users',
    ];

    /**
     * Child-first order. Foreign keys remain enabled for the whole operation.
     *
     * @var list<string>
     */
    private const PURGE_TABLES = [
        'cache_locks',
        'cache',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'notifications',
        'audit_logs',
        'integration_idempotency_keys',
        'integration_logs',
        'system_command_runs',
        'system_heartbeats',
        'attachments',
        'temporary_media_uploads',
        'client_review_tokens',
        'marketing_campaign_post_version_media',
        'marketing_campaign_post_comments',
        'marketing_campaign_post_publications',
        'marketing_campaign_post_versions',
        'marketing_campaign_post_media',
        'marketing_campaign_posts',
        'shoot_slots',
        'shoots',
        'task_checklist_items',
        'task_comments',
        'tasks',
        'ticket_checklist_items',
        'ticket_comments',
        'tickets',
        'calendar_events',
        'electronic_invoice_events',
        'electronic_invoice_transmissions',
        'payments',
        'invoice_items',
        'marketing_campaign_extras',
        'marketing_campaign_periods',
        'invoices',
        'invoice_number_sequences',
        'billing_profiles',
        'expenses',
        'hosting_service_interventions',
        'hosting_services',
        'client_social_accounts',
        'agency_social_assets',
        'agency_social_connections',
        'chatbot_marketing_campaigns',
        'chatbot_marketing_posts',
        'chatbot_projects',
        'chatbot_tickets',
        'chatbot_client_sessions',
        'chatbot_clients',
        'project_user',
        'projects',
        'team_user',
        'teams',
        'user_daily_note_checklist_items',
        'user_daily_note_entries',
        'user_daily_notes',
        'marketing_campaigns',
        'clients',
    ];

    public function run(): void
    {
        $command = $this->console();
        $admin = $this->resolveAdmin($command);

        $this->assertSupportedSchema($command);
        $this->assertNoProductionElectronicInvoiceTransmissions($command);
        $rowsToDelete = array_sum($this->countRowsToDelete($admin->getKey()));

        try {
            $this->purge($admin->getKey());
        } catch (Throwable $exception) {
            report($exception);
            $command->fail(
                'Pulizia non completata. La transazione e stata annullata e i dati nel database non sono stati modificati.'
            );
        }

        $command->info('Pulizia completata. E stato conservato soltanto l\'account amministratore.');
        $command->line('Righe eliminate: '.$rowsToDelete.'.');
        $command->warn('Le cartelle Nextcloud, i file fisici e i dati presso servizi esterni non sono stati cancellati.');
    }

    private function console(): Command
    {
        if (! isset($this->command)) {
            throw new RuntimeException(
                'PurgeDemoDataSeeder must be run through the Artisan db:seed command.'
            );
        }

        return $this->command;
    }

    private function resolveAdmin(Command $command): User
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            $command->fail('Pulizia bloccata: non esiste alcun account amministratore attivo da conservare.');
        }

        return $admin;
    }

    private function assertSupportedSchema(Command $command): void
    {
        $actualTables = collect(Schema::getTableListing(Schema::getCurrentSchemaName(), false))
            ->reject(fn (string $table): bool => str_starts_with($table, 'sqlite_'))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $expectedTables = collect([...self::PRESERVED_TABLES, ...self::PURGE_TABLES])
            ->unique()
            ->sort()
            ->values()
            ->all();
        $unknownTables = array_values(array_diff($actualTables, $expectedTables));
        $missingTables = array_values(array_diff($expectedTables, $actualTables));

        if ($unknownTables === [] && $missingTables === []) {
            return;
        }

        if ($unknownTables !== []) {
            $command->line('Tabelle non riconosciute: '.implode(', ', $unknownTables));
        }

        if ($missingTables !== []) {
            $command->line('Tabelle mancanti: '.implode(', ', $missingTables));
        }

        $command->fail(
            'Pulizia bloccata: la struttura del database non coincide con quella verificata dal seeder.'
        );
    }

    private function assertNoProductionElectronicInvoiceTransmissions(Command $command): void
    {
        if (! $this->hasProductionElectronicInvoiceTransmissions()) {
            return;
        }

        $command->fail(
            'Pulizia bloccata: sono presenti invii di fatture elettroniche effettuati verso l\'ambiente Aruba di produzione.'
        );
    }

    /**
     * @return array<string, int>
     */
    private function countRowsToDelete(int|string $adminId): array
    {
        $counts = [];

        foreach (self::PURGE_TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $counts['users'] = DB::table('users')
            ->where('id', '<>', $adminId)
            ->count();

        return $counts;
    }

    private function hasProductionElectronicInvoiceTransmissions(): bool
    {
        return DB::table('electronic_invoice_transmissions')
            ->where('environment', 'production')
            ->where('mode', 'live')
            ->exists();
    }

    private function purge(int|string $adminId): void
    {
        DB::transaction(function () use ($adminId): void {
            $admin = DB::table('users')
                ->where('id', $adminId)
                ->lockForUpdate()
                ->first();

            if ($admin === null || $admin->role !== UserRole::Admin->value || $admin->status !== 'active') {
                throw new RuntimeException('The administrator selected for retention is no longer valid.');
            }

            if ($this->hasProductionElectronicInvoiceTransmissions()) {
                throw new RuntimeException('A production electronic invoice transmission appeared during cleanup.');
            }

            DB::table('marketing_campaign_post_publications')
                ->whereNotNull('retry_of_publication_id')
                ->update(['retry_of_publication_id' => null]);

            foreach (self::PURGE_TABLES as $table) {
                DB::table($table)->delete();
            }

            DB::table('users')
                ->where('id', '<>', $adminId)
                ->delete();

            DB::table('users')
                ->where('id', $adminId)
                ->update([
                    'remember_token' => Str::random(60),
                    'last_tickets_viewed_at' => null,
                    'updated_at' => now(),
                ]);

            if (array_sum($this->countRowsToDelete($adminId)) !== 0) {
                throw new RuntimeException('Rows remained after the cleanup transaction.');
            }
        });
    }
}
