<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'expenseable_type',
        'expenseable_id',
        'title',
        'description',
        'amount',
        'category',
        'supplier',
        'expense_date',
        'due_date',
        'paid_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function expenseable()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->whereNotNull('due_date')
                     ->where('due_date', '<', now()->startOfDay());
    }

    public function scopeForExpenseable($query, $model)
    {
        return $query->where('expenseable_type', get_class($model))
                     ->where('expenseable_id', $model->id);
    }

    // Accessors
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending'
            && $this->due_date
            && $this->due_date->lt(today());
    }
}
