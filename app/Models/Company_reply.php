<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company_reply extends Model
{
    public $timestamps = true;
	
    protected $fillable = [
        'company_review_id',
        'reply_by',
		'reply_to',
	    'reply',
		'status',
	    'created_at',
	    'updated_at',
    ];
    public function company_review()
	{
		return $this->belongsTo(Company_review::class);
	}
    public function reply_by()
	{
		return $this->belongsTo(User::class, 'reply_by', 'id');
	}
    public function reply_to()
	{
		return $this->belongsTo(User::class, 'reply_to', 'id');
	}
	
}
