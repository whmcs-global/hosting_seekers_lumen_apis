<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserServer extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'order_id',
        'company_server_package_id',
		'name',
		'domain',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
	public function company_server_package()
	{
	   return $this->belongsTo(CompanyServerPackage::class);
	}
}
