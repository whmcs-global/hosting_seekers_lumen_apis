<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'user_id',
       	'ip_address',
       	'status',
	    'created_at',
	    'updated_at',
    ];
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
}
