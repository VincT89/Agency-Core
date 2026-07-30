<?php

namespace App\Domain\Social\Enums;

enum VersionMediaBackfillClassification: string
{
    case AlreadyPopulated = 'already_populated';
    case DeterministicallyResolvable = 'deterministically_resolvable';
    case Ambiguous = 'ambiguous';
    case Unresolvable = 'unresolvable';
    case ForeignMedia = 'foreign_media';

    public function isSafeToApply(): bool
    {
        return $this === self::DeterministicallyResolvable;
    }

    public function requiresAttention(): bool
    {
        return in_array($this, [
            self::Ambiguous,
            self::Unresolvable,
            self::ForeignMedia,
        ], true);
    }
}
