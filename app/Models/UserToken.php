<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserToken extends Model
{
    // protected $connection = 'mysql2';
    public $timestamps = true;
    protected $fillable = [
        'user_id', 'access_token','device_id','device_name'
    ];
}
