<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $timestamps = true;
    protected $fillable = [
		'name',
		'icon',
		'assign_to_products',
		'status',
	    'created_at',
	    'updated_at',
    ];
	public $sortable = [ 
		'id',
       	'name',
		'icon',
		'assign_to_products',
		'status',
	    'created_at',
	    'updated_at',
    ];
    public function membership_plan()
	{
		return $this->hasOne(Membership_plan::class);
	}
    public function user()
	{
		return $this->hasOne(User::class);
	}
	public function company_service()
	{
	   return $this->hasMany(Company_service::class);
	}
	public function company_server_product()
	{
	   return $this->hasMany(CompanyServerProduct::class);
	}
	public function company_server_package()
	{
	   return $this->hasMany(CompanyServerPackage::class);
	}
    public function ordered_product()
	{
		return $this->hasMany(OrderedProduct::class);
	}
    public function order()
	{
		return $this->hasMany(Order::class);
	}
	public function company_server_product_price()
	{
	   return $this->hasMany(CompanyServerProductPrice::class);
	}
    public function order_transaction()
	{
		return $this->hasMany(OrderTransaction::class);
	}
    public function withdrawal_payment()
	{
		return $this->hasMany(WithdrawalPayment::class);
	}
    public function wallet_payment()
	{
		return $this->hasMany(WalletPayment::class);
	}
}
