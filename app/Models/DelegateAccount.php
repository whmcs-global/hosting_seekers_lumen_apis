<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegateAccount extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'user_id',
       	'delegate_user_id',
       	'status',
	    'created_at',
	    'updated_at',
    ];
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
    public function delegate_user()
	{
		return $this->belongsTo(User::class, 'delegate_user_id', 'id');
	}
	public function delegate_domain_access()
	{
	   return $this->hasMany(DelegateDomainAccess::class, 'delegate_account_id', 'id');
	}
}
