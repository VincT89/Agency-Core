<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveAt(Builder $query, CarbonInterface $moment): Builder
    {
        $time = $moment->format('H:i:s');

        return $query
            ->whereDate('date', $moment->toDateString())
            ->where('starts_at', '<=', $time)
            ->where('ends_at', '>', $time);
    }

    public function scopeCurrentOrNextAt(Builder $query, CarbonInterface $moment): Builder
    {
        /** An active slot takes priority; an early confirmation can only target the next slot of the same local day. */
        return $query->whereDate('date', $moment->toDateString())
            ->where('ends_at', '>', $moment->format('H:i:s'))->orderBy('starts_at')->orderBy('id');
    }

    public function startsAtForInput(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function endsAtForInput(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }

    public function timeRangeLabel(): string
    {
        return $this->startsAtForInput().'–'.$this->endsAtForInput();
    }
}
