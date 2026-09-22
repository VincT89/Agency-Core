<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price', 'total', 'sort_order'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
}
