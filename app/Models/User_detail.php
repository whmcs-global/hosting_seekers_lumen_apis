<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User_detail extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id','first_name','last_name','address','row_data'
    ];
}
