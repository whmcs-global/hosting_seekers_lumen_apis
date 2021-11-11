<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPlansHistory extends Model
{
    protected $table = 'user_plans_history';
    public $timestamps = false;
    protected $fillable = [
        'plan_id','user_id','order_id','status'
    ];
}
