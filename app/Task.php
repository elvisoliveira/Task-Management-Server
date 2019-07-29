<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'assigned_by',
        'assigned_to',
        'due_date',
        'title',
        'description',
        'status',
        'deleted_at',
        'assigned_at',
        'completed_at',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at',
    ];

    public function assigned_to()
    {
        return $this->belongsTo('App\User', 'assigned_to');
    }

    public function assigned_by()
    {
        return $this->belongsTo('App\User', 'assigned_by');
    }
}
