<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WithdrawalPayment extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'payment_mode',
        'currency_id',
        'amount',
        'comments',
        'raw_data',
        'status',
	    'created_at',
	    'updated_at',
    ]; 
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
    public function company()
	{
		return $this->belongsTo(User::class, 'company_id', 'id');
	}
	public function currency()
	{
	   return $this->belongsTo(Currency::class);
	}
}
