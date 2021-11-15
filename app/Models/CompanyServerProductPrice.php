<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyServerProductPrice extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
        'company_server_product_id',
        'currency_id',
        'price',
        'billing_cycle',
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
	   return $this->belongsTo(CompanyServerProduct::class, 'company_server_product_id');
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
}
