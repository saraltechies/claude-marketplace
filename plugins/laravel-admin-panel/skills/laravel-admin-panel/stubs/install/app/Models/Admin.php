<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $fillable = ['username', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];
}
