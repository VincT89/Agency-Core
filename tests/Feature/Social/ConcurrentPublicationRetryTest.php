<?php

namespace Tests\Feature\Social;

use App\Enums\Social\PublicationStatus;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentPublicationRetryTest extends TestCase
{
    private string $originalConnection;

    private mixed $originalDatabase;

    private ?string $sqliteDatabasePath = null;

    private ?string $tempScriptPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        $this->originalDatabase = config(
            "database.connections.{$this->originalConnection}.database"
        );

        $this->configureSharedTestDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);

        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->tempScriptPath = $directory.
            DIRECTORY_SEPARATOR.
            'concurrent_retry_'.
            Str::uuid().
            '.php';
    }

    protected function tearDown(): void
    {
        if ($this->tempScriptPath && file_exists($this->tempScriptPath)) {
            unlink($this->tempScriptPath);
        }

        DB::disconnect(config('database.default'));

        if ($this->sqliteDatabasePath && file_exists($this->sqliteDatabasePath)) {
            unlink($this->sqliteDatabasePath);
        }

        if (
            $this->originalConnection === 'mysql' &&
            $this->originalDatabase === 'agency_core_codex_test_pr8_11'
        ) {
            DB::connection('mysql_admin')->statement(
                'DROP DATABASE IF EXISTS `agency_core_codex_test_pr8_11`'
            );
            DB::purge('mysql_admin');
        }

        config([
            'database.default' => $this->originalConnection,
            "database.connections.{$this->originalConnection}.database" => $this->originalDatabase,
        ]);
        DB::purge($this->originalConnection);

        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];

        parent::tearDown();
    }

    public function test_concurrent_retries_only_succeed_once(): void
    {
        $post = MarketingCampaignPost::factory()->create();
        $version = MarketingCampaignPostVersion::factory()->create([
            'marketing_campaign_post_id' => $post->id,
        ]);

        $publication = MarketingCampaignPostPublication::factory()->create([
            'status' => PublicationStatus::Failed,
            'snapshot_schema_version' => 1,
            'attempt_count' => 1,
            'idempotency_key' => 'concurrent_'.Str::uuid(),
            'snapshot_hash' => str_repeat('a', 64),
            'payload_snapshot' => ['dummy' => 'data'],
            'marketing_campaign_post_id' => $post->id,
            'marketing_campaign_post_version_id' => $version->id,
        ]);

        $this->writeWorkerScript($publication->id);

        $php = (new PhpExecutableFinder)->find() ?: 'php';
        $processes = [];

        for ($worker = 0; $worker < 3; $worker++) {
            $process = new Process(
                [$php, $this->tempScriptPath],
                base_path(),
                $this->childEnvironment()
            );
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        $outputs = [];
        foreach ($processes as $process) {
            $process->wait();
            $outputs[] = trim($process->getOutput().$process->getErrorOutput());
        }

        $successCount = count(array_filter(
            $outputs,
            static fn (string $output): bool => str_contains($output, 'SUCCESS')
        ));

        $this->assertSame(
            1,
            $successCount,
            "Era atteso un solo retry riuscito. Output:\n".implode("\n---\n", $outputs)
        );
        $this->assertSame(
            PublicationStatus::Superseded,
            $publication->fresh()->status
        );
        $this->assertSame(
            1,
            MarketingCampaignPostPublication::where(
                'retry_of_publication_id',
                $publication->id
            )->count()
        );
    }

    private function configureSharedTestDatabase(): void
    {
        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $directory = storage_path('framework/testing');
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $this->sqliteDatabasePath = $directory.
                DIRECTORY_SEPARATOR.
                'concurrent_'.
                Str::uuid().
                '.sqlite';
            touch($this->sqliteDatabasePath);

            config(['database.connections.sqlite.database' => $this->sqliteDatabasePath]);
            DB::purge('sqlite');

            return;
        }

        if ($connection !== 'mysql') {
            throw new \RuntimeException(
                "Il test concorrente supporta solo sqlite condiviso o mysql, non {$connection}."
            );
        }

        $database = (string) config('database.connections.mysql.database');
        $isExplicitTestDatabase = $database === 'testing'
            || str_ends_with($database, '_test')
            || str_starts_with($database, 'test_');

        if (
            ! app()->environment('testing')
            || ! $isExplicitTestDatabase
            || $database === ''
            || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1
        ) {
            throw new \RuntimeException(
                'Il test concorrente richiede un database MySQL esplicitamente dedicato ai test.'
            );
        }

        config([
            'database.connections.mysql_admin' => [
                ...config('database.connections.mysql'),
                'database' => null,
            ],
        ]);
        DB::purge('mysql_admin');
        DB::connection('mysql_admin')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` ".
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        DB::purge('mysql');
    }

    private function writeWorkerScript(int $publicationId): void
    {
        $basePath = var_export(base_path(), true);
        $script = <<<PHP
<?php
\$basePath = {$basePath};
require \$basePath . '/vendor/autoload.php';
\$app = require \$basePath . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

\$verifier = Mockery::mock(
    App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class
);
\$verifier->shouldReceive('verify')
    ->once()
    ->andReturn(new App\Domain\Social\DTOs\PublicationIntegrityResult(true));
\$app->instance(
    App\Domain\Social\Services\MarketingCampaignPostPublicationIntegrityVerifier::class,
    \$verifier
);

usleep(250000);
\$publication = App\Models\MarketingCampaignPostPublication::findOrFail({$publicationId});
\$action = \$app->make(
    App\Domain\Social\Actions\RetryMarketingCampaignPostPublicationAction::class
);

try {
    \$action->execute(\$publication);
    echo 'SUCCESS';
} catch (Throwable \$exception) {
    echo 'FAILED:' . get_class(\$exception);
}
PHP;

        file_put_contents($this->tempScriptPath, $script);
    }

    private function childEnvironment(): array
    {
        $connection = config('database.default');
        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
            'DB_URL' => '',
            'DB_CONNECTION' => $connection,
            'DB_DATABASE' => (string) config(
                "database.connections.{$connection}.database"
            ),
        ];

        if ($connection === 'mysql') {
            foreach (['host', 'port', 'username', 'password'] as $key) {
                $environment['DB_'.strtoupper($key)] = (string) config(
                    "database.connections.mysql.{$key}"
                );
            }
        }

        return $environment;
    }
}
