<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderedProduct extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'order_id',
        'company_server_product_id',
		'name',
		'price',
		'currency_id',
		'billing_cycle',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
	public function product()
	{
	   return $this->belongsTo(CompanyServerProduct::class, 'company_server_product_id');
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
}
