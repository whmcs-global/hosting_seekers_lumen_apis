<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    public function user_detail()
	{
		return $this->hasOne(User_detail::class);
	}
    public function company_location()
	{
		return $this->hasMany(Company_location::class);
	}
	public function company_server_location()
	{
	   return $this->hasMany(CompanyServerLocation::class);
	}
}
