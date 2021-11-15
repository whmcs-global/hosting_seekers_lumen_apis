<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyServersProduct extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
	    'company_server_id',
        'company_server_product_id',
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
	public function company_server()
	{
	   return $this->belongsTo(CompanyServer::class, 'company_server_id');
	}
}
