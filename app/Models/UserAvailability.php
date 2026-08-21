<?php

namespace App\Models;

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
