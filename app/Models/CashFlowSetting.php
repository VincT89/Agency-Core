<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlowSetting extends Model
{
    protected $fillable = ['id', 'opening_balance', 'balance_date', 'updated_by'];

    protected $casts = ['opening_balance' => 'decimal:2', 'balance_date' => 'immutable_date'];
}
