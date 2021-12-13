<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegateDomainAccess extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'delegate_account_id',
       	'user_server_id',
       	'status',
	    'created_at',
	    'updated_at',
    ];
    public function user_server()
	{
		return $this->belongsTo(UserServer::class, 'user_server_id', 'id');
	}
	public function delegate_account()
	{
	   return $this->belongsTo(DelegateAccount::class, 'delegate_account_id', 'id');
	}
    public function delegate_domain_access()
	{
		return $this->hasMany(DelegateDomainAccessPermission::class, 'delegate_domain_access_id', 'id');
	}
}
