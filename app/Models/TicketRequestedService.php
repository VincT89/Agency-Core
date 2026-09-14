<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketRequestedService extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'description', 'sort_order'];
}
