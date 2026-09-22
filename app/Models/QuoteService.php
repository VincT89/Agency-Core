<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteService extends Model
{
    public const FIELDS = ['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price'];

    protected $fillable = ['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price', 'fingerprint', 'created_by'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
}
