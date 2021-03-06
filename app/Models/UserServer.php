<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserServer extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'id',
		'user_id',
		'company_id',
		'order_id',
        'company_server_package_id',
		'name',
		'domain',
		'ns_detail',
        'cloudfare_user_id',
        'cloudfare_id',
		'screenshot',
		'status',
	    'created_at',
	    'updated_at',
    ]; 
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
    public function cloudfare_user()
	{
		return $this->belongsTo(CloudfareUser::class, 'cloudfare_user_id', 'id');
	}
    public function company()
	{
		return $this->belongsTo(User::class, 'company_id', 'id');
	}
	public function order()
	{
	   return $this->belongsTo(Order::class);
	}
	public function company_server_package()
	{
	   return $this->belongsTo(CompanyServerPackage::class);
	}
	public function delegate_domain_access()
	{
	   return $this->hasOne(DelegateDomainAccess::class, 'user_server_id', 'id');
	}
	public function bandwidth()
	{
	   return $this->hasMany(DomainBandwidthStat::class, 'user_server_id', 'id');
	}
}
