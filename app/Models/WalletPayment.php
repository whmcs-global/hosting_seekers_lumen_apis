<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletPayment extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
        'user_id',
        'credit_by',
        'payment_mode',
        'currency_id',
        'amount',
        'comments',
        'order_id',
        'order_transaction_id',
        'raw_data',
        'status'
    ];  
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
	public function order_transaction()
	{
	   return $this->belongsTo(OrderTransaction::class);
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
}
