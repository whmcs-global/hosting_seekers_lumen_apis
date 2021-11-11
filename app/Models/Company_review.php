<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company_review extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
		'uid',
        'company_id',
        'user_id',
		'rate_point',
	    'review',
	    'review_data',
		'status',
       	'zipcode',
	    'created_at',
	    'updated_at',
    ];
	
    public function getReviewDataAttribute($value)
    {
        return unserialize($value);
	}
    public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}
    public function company()
	{
		return $this->belongsTo(User::class, 'company_id', 'id');
	}
	public function company_reply()
	{
	   return $this->hasMany(Company_reply::class);
	}
	public function review_complaint()
	{
	   return $this->hasOne(ReviewComplaint::class);
	}
}
