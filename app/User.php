<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

// use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

//blow commented code were copied from - https://www.cloudways.com/blog/lumen-rest-api-authentication/

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Contracts\Auth\Authenticatable;
// use Illuminate\Auth\Authenticatable as AuthenticableTrait;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','role','created_by','deleted_by','deleted_at',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    // public function assignedTasks()
    // {
    //     return $this->hasMany('App\Task', 'assignee_id');
    // }

    // public function createdTasks()
    // {
    //     return $this->hasMany('App\Task', 'assigner_id');
    // }
}
