<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'order_id',
        'user_id',
		'ipn_id',
        'currency_id',
		'amount',
		'payable_amount',
		'trans_id',
		'trans_details',
        'trans_status',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
	public $sortable = [
		'id',
		'amount',
		'status',
	 	'created_at',
		'updated_at',
    ];
	public function user()
	{
	   return $this->belongsTo(User::class);
	}
    public function invoice()
	{
		return $this->hasMany(Invoice::class);
	}
    public function ordered_product()
	{
		return $this->hasOne(OrderedProduct::class);
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
    public function user_server()
	{
		return $this->hasOne(UserServer::class);
	}
    public function order_transaction()
	{
		return $this->hasMany(OrderTransaction::class);
	}
}
