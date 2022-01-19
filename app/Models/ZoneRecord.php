<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneRecord extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'records';
    public $timestamps = false;
    protected $fillable = [
		'domain_id',
        'name',
        'type',
        'content',
        'ttl',
        'change_date'
    ];
}
