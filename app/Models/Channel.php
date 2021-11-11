<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'language_id', 'category_id','region_id'
    ];
}
