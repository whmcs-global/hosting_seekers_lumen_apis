<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneDomain extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'domains';
    public $timestamps = false;
    protected $fillable = [
		'name',
        'master',
        'last_check',
        'type',
        'notified_serial',
        'account',
    ];
}
