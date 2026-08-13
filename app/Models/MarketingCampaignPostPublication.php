<?php

namespace App\Models;

use App\Enums\Social\PublicationFailureClassification;
use App\Enums\Social\PublicationStatus;
use App\Enums\Social\SocialPlatform;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'status' => PublicationStatus::class,
            'platform' => SocialPlatform::class,
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
            'failure_classification' => PublicationFailureClassification::class,
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

    protected function resolvedExternalPermalink(): Attribute
    {
        return Attribute::get(function (): ?string {
            $storedUrl = $this->safeExternalUrl($this->external_permalink);
            if ($storedUrl) {
                return $storedUrl;
            }

            foreach ([$this->provider_last_response, $this->response_snapshot] as $payload) {
                $providerUrl = $this->findProviderUrl($payload);
                if ($providerUrl) {
                    return $providerUrl;
                }
            }

            $postId = trim((string) $this->external_post_id);
            if ($postId === '') {
                $postId = (string) ($this->singleProviderIdentifier(
                    $this->findProviderValue(
                        [$this->provider_last_response, $this->response_snapshot],
                        ['publicaly_available_post_id', 'publicly_available_post_id']
                    )
                ) ?? '');
            }

            if ($postId === '') {
                return null;
            }

            if ($this->platform === SocialPlatform::Facebook) {
                if (str_contains($postId, '_')) {
                    [$pageId, $storyId] = explode('_', $postId, 2);

                    return 'https://www.facebook.com/'.rawurlencode($pageId).'/posts/'.rawurlencode($storyId);
                }

                return 'https://www.facebook.com/'.rawurlencode($postId);
            }

            if ($this->platform === SocialPlatform::Tiktok) {
                $username = $this->tiktokUsername();

                return $username !== null
                    ? 'https://www.tiktok.com/@'.rawurlencode($username).'/video/'.rawurlencode($postId)
                    : null;
            }

            return null;
        });
    }

    private function findProviderUrl(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (in_array($key, ['permalink', 'permalink_url', 'share_url'], true)) {
                $url = $this->safeExternalUrl($value);
                if ($url) {
                    return $url;
                }
            }

            if (is_array($value) && ($nested = $this->findProviderUrl($value))) {
                return $nested;
            }
        }

        return null;
    }

    private function singleProviderIdentifier(mixed $value): ?string
    {
        $values = is_array($value) ? $value : [$value];
        $identifiers = [];

        foreach ($values as $identifier) {
            if (! is_string($identifier) && ! is_int($identifier)) {
                continue;
            }

            $identifier = trim((string) $identifier);
            if ($identifier !== '') {
                $identifiers[] = $identifier;
            }
        }

        $identifiers = array_values(array_unique($identifiers));

        return count($identifiers) === 1 ? $identifiers[0] : null;
    }

    private function tiktokUsername(): ?string
    {
        foreach ([
            $this->socialAccount?->username,
            data_get(
                $this->socialAccount?->api_metadata,
                'content_posting_info.creator_username'
            ),
        ] as $value) {
            if (! is_string($value)) {
                continue;
            }

            $username = ltrim(trim($value), '@');
            if ($username !== '') {
                return $username;
            }
        }

        return null;
    }

    private function findProviderValue(mixed $payload, array $keys): mixed
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (in_array($key, $keys, true)) {
                return $value;
            }

            if (is_array($value)) {
                $nested = $this->findProviderValue($value, $keys);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function safeExternalUrl(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $value
            : null;
    }

    protected static function booted(): void
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
