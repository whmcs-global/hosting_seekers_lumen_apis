<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'plan_id','price','transaction_id','payment_by','status'
    ];
}
