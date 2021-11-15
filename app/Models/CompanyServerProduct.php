<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyServerProduct extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
	    'name',
        'currency_id',
       	'price',
	    'billing_cycle',
	    'features',
		'status',
	    'created_at',
	    'updated_at',
    ];
    public function user()
	{
		return $this->belongsTo(User::class);
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
	public function company_servers_product()
	{
	   return $this->hasMany(CompanyServersProduct::class);
	}
	public function company_server_package()
	{
	   return $this->hasMany(CompanyServerPackage::class);
	}
    public function ordered_product()
	{
		return $this->hasMany(OrderedProduct::class);
	}
	public function company_server_product_price()
	{
	   return $this->hasMany(CompanyServerProductPrice::class);
	}
}
