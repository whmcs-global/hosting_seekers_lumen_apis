<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactDetail extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'first_name', 'last_name','email','mobile','message','status'
    ];
}
