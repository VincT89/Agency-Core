<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignPostPublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_campaign_post_id',
        'marketing_campaign_post_version_id',
        'client_social_account_id',
        'platform',
        'status',
        'meta_processing_state',
        'delivery_state',
        'external_post_id',
        'external_container_id',
        'external_task_id',
        'external_permalink',
        'snapshot_schema_version',
        'snapshot_hash',
        'idempotency_key',
        'payload_snapshot',
        'response_snapshot',
        'provider_state_payload',
        'provider_last_response',
        'error_message',
        'published_at',
        'correlation_id',
        'publishing_started_at',
        'stale_deadline_at',
        'attempt_count',
        'poll_count',
        'retry_of_publication_id',
        'failure_classification',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\Social\PublicationStatus::class,
            'platform' => \App\Enums\Social\SocialPlatform::class,
            'snapshot_schema_version' => 'integer',
            'payload_snapshot' => 'array',
            'response_snapshot' => 'array',
            'provider_state_payload' => 'array',
            'provider_last_response' => 'array',
            'published_at' => 'datetime',
            'publishing_started_at' => 'datetime',
            'stale_deadline_at' => 'datetime',
            'attempt_count' => 'integer',
            'poll_count' => 'integer',
            'failure_classification' => \App\Enums\Social\PublicationFailureClassification::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(ClientSocialAccount::class, 'client_social_account_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignPostVersion::class, 'marketing_campaign_post_version_id');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_publication_id');
    }

    protected static function booted()
    {
        static::updating(function ($publication) {
            $immutableFields = [
                'marketing_campaign_post_id',
                'marketing_campaign_post_version_id',
                'client_social_account_id',
                'platform',
                'snapshot_schema_version',
                'snapshot_hash',
                'payload_snapshot',
                'idempotency_key',
                'attempt_count',
                'retry_of_publication_id',
                'correlation_id',
            ];

            foreach ($immutableFields as $field) {
                if ($publication->isDirty($field)) {
                    throw new \Exception("Tentativo di modifica di un campo immutabile ({$field}) nello snapshot della publication.");
                }
            }
        });
    }
}
