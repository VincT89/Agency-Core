<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'parent_id', 'identity_key', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public static function identity(string $name, ?int $parentId): string
    {
        return hash('sha256', ($parentId ?? 0).':'.mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name))));
    }
}
