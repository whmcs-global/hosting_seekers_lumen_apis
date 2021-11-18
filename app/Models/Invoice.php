<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'invoice_id',
		'order_id',
        'user_id',
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
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
    public function order_transaction()
	{
		return $this->hasMany(OrderTransaction::class);
	}
}
