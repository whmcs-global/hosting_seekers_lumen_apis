<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public $timestamps = true;
    protected $fillable = [
       	'to_email',
       	'auto_responder_id',
        'is_read',
        'template_name',
        'token',
	    'created_at',
	    'updated_at',
    ];
    public function AutoResponder()
	{
		return $this->belongsTo(AutoResponder::class);
	}
}
