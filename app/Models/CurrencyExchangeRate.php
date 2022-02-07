<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyExchangeRate extends Model
{
    public $timestamps = true;
    protected $fillable = [
		'rates',
		'status',
	    'created_at',
	    'updated_at',
    ];
}
