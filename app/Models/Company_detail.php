<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company_detail extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
       	'user_id',
		'company_name',
	    'about',
		'website_url',
       	'contact_person_mobile',
	    'contact_person_email',
		'toll_free_number ',
       	'customer_care_number',
	    'google_plus_link',
		'facebook_link',
		'twitter_link',
		'instagram_link',
		'status',
		'is_highlighted',
		'average_rating',
		'profile_stats',
		'total_views',
		'total_reviews',
		'slug',
	    'created_at',
	    'updated_at',
    ];
	protected $searchable = [
        'company_name',
        'about',
    ];
    public function user()
	{
		return $this->belongsTo(User::class);
	}
}
