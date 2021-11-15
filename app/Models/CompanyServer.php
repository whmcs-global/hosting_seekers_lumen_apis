<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyServer extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
		'name',
	    'host',
       	'ip_address',
	    'name_servers',
	    'link_server',
		'city',
		'country_id',
		'state_id',
		'status',
	    'created_at',
	    'updated_at',
    ];
    public function user()
	{
		return $this->belongsTo(User::class);
	}
	public function product()
	{
	   return $this->hasMany(CompanyServersProduct::class);
	}
	public function company_server_package()
	{
	   return $this->hasMany(CompanyServerPackage::class);
	}
	public function country()
	{
	   return $this->belongsTo(Country::class);
	}
	public function state()
	{
	   return $this->belongsTo(State::class);
	}
}
