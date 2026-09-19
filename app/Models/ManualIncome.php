<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualIncome extends Model
{
    public const STATUSES = ['expected' => 'Da incassare', 'received' => 'Incassata', 'cancelled' => 'Annullata'];

    protected $fillable = ['user_id', 'client_id', 'title', 'payer', 'amount', 'expected_on', 'received_on', 'status', 'notes'];

    protected $casts = ['amount' => 'decimal:2', 'expected_on' => 'date', 'received_on' => 'date'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
