<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Laravel\Sanctum\HasApiTokens;

class User extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasApiTokens;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed'];
}
