<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivePlan extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'plan_id','region_id','name','allow_profiles','price','currency_id','duration','status','lat_location'
    ];
}
