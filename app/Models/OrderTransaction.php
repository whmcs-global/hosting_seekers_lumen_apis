<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTransaction extends Model
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
	public function user()
	{
	   return $this->belongsTo(User::class);
	}
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
}
