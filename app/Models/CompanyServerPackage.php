<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyServerPackage extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
        'company_server_product_id',
        'company_server_id',
		'details',
		'package',
		'status',
	    'created_at',
	    'updated_at',
    ];
    public function user()
	{
		return $this->belongsTo(User::class);
	}
	public function company_server_product()
	{
	   return $this->belongsTo(CompanyServerProduct::class);
	}
	public function company_server()
	{
	   return $this->belongsTo(CompanyServer::class);
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
    public function user_server()
	{
		return $this->hasOne(UserServer::class);
	}
}
