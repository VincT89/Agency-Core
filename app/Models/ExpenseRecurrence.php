<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseRecurrence extends Model
{
    public const FREQUENCIES = ['weekly' => 'Settimanale', 'monthly' => 'Mensile', 'quarterly' => 'Trimestrale', 'semiannual' => 'Semestrale', 'yearly' => 'Annuale'];

    protected $fillable = ['user_id', 'title', 'amount', 'category', 'supplier', 'document_kind', 'frequency', 'starts_on', 'ends_on', 'active', 'notes'];

    protected $casts = ['amount' => 'decimal:2', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'active' => 'boolean'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function occurrence(int $index): CarbonImmutable
    {
        if ($this->frequency === 'weekly') {
            return $this->starts_on->addWeeks($index);
        }
        $months = match ($this->frequency) {
            'monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12,
        };

        // Always calculate from the original day: 31 Jan, 28 Feb, 31 Mar.
        return $this->starts_on->addMonthsNoOverflow($months * $index);
    }
}
