<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewCriteria extends Model
{
    public $timestamps = true;
    protected $fillable = [
    	'name',
		'detail',
		'status',
		'created_at',
		'updated_at',
    ];
}
