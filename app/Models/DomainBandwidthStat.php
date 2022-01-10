<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainBandwidthStat extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'user_server_id',
        'stats_date',
		'bandwidth',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
    public function user_server()
	{
		return $this->belongsTo(UserServer::class);
	}
}
