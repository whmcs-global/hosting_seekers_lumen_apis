<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTerminatedAccount extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'user_id',
		'name',
		'domain',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
}
