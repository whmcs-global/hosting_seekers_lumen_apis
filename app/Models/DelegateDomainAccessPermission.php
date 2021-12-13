<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegateDomainAccessPermission extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'delegate_domain_access_id',
       	'delegate_permission_id',
       	'status',
	    'created_at',
	    'updated_at',
    ];
    public function delegate_domain_access()
	{
		return $this->belongsTo(DelegateDomainAccess::class, 'delegate_domain_access_id', 'id');
	}
	public function delegate_permission()
	{
	   return $this->belongsTo(DelegatePermission::class, 'delegate_permission_id', 'id');
	}
}
