<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegatePermission extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'name',
       	'slug',
       	'status',
	    'created_at',
	    'updated_at',
    ];
	public function delegate_access()
	{
	   return $this->hasMany(DelegateDomainAccessPermission::class, 'delegate_permission_id', 'id');
	}
}
