<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudfareUser extends Model
{
    public $timestamps = true;
    protected $fillable = [
    	'name',
		'email',
		'password',
		'user_key',
		'user_api',
		'domain_count',
		'status',
		'created_at',
		'updated_at',
    ];
	public function user_server()
	{
	   return $this->hasMany(UserServer::class);
	}
}
