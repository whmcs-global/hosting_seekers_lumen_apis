<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'user_profiles';
    
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'name', 'last_login', 'status','row_data'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    
	public function user()
	{
	   return $this->belongsTo(User::class, 'user_id', 'id');
	}
}
