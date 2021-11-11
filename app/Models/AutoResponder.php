<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoResponder extends Model
{
    public $timestamps = true;
    protected $fillable = [
       	'subject',
       	'template',
       	'template_name',
       	'status',
	    'created_at',
	    'updated_at',
    ];
    public function EmailLog()
	{
		return $this->hasMany(EmailLog::class);
	}
}
