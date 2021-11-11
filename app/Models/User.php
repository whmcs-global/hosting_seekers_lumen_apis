<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticableTrait;
class User extends Model implements Authenticatable
{
    use AuthenticableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    
    public $timestamps = true;
    protected $fillable = [        
        'first_name', 
        'last_name',
		'email',
		'ip_address',
		'raw_data',
		'password',
		'social_type',
		'social_id',
		'created_at',
		'updated_at',
		'status',
		'show_in_front',
		'verified',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];
    public function getLastNameAttribute($value)
    {
        return ucfirst($value);
	}
	
    public function getFirstNameAttribute($value)
    {
        return ucfirst($value);
	}
	
	public function user_detail()
	{
	   return $this->hasOne(User_detail::class);
	}
	public function company_detail()
	{
	   return $this->hasOne(Company_detail::class);
	}
	public function company_location()
	{
	   return $this->hasMany(Company_location::class);
	}
	public function company_server_location()
	{
	   return $this->hasMany(CompanyServerLocation::class);
	}
	public function company_photo()
	{
	   return $this->hasMany(Company_photo::class);
	}
	public function company_service()
	{
	   return $this->hasMany(Company_service::class);
	}
	public function company_view()
	{
	   return $this->hasMany(CompanyView::class, 'company_id', 'id');
	}
	public function company_tag()
	{
	   return $this->hasMany(Company_tag::class);
	}
	public function company_coupon()
	{
	   return $this->hasMany(Company_coupon::class);
	}
	public function company_category()
	{
	   return $this->hasMany(CompanyCategory::class);
	}
	public function company_review()
	{
	   return $this->hasMany(Company_review::class, 'user_id', 'id');
	}
	public function company_rating()
	{
	   return $this->hasMany(Company_review::class, 'company_id', 'id');
	}
	public function company_payment_method()
	{
	   return $this->hasMany(Company_payment_method::class);
	}
	
	public function company_contact_request()
	{
	   return $this->hasMany(Company_contact_request::class);
	}
	public function company_active_plan()
	{
	   return $this->hasOne(Company_active_plan::class);
	}
	public function badges()
	{
	   return $this->hasMany(CompanyBadge::class);
	}
	public function audience()
	{
	   return $this->hasMany(AudienceGroup::class);
	}
	public function review_complaint()
	{
	   return $this->hasMany(ReviewComplaint::class, 'user_id', 'id');
	}
	public function company_review_complaint()
	{
	   return $this->hasMany(ReviewComplaint::class, 'company_id', 'id');
	}
}
