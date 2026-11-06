<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Projects extends Model
{
    //

    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by'
    ];

    protected function casts():array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date'
        ];
    }

    public function created_by_user(){
        return $this-> belongsTo(User::class,'created_by','id');
    }

    // this is for the project members
    // in order to fetch who are the users in the project

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members', 'project_id', 'user_id')
            ->withTimestamps(); // only if the pivot table has created_at/updated_at
    }

    public function tasks(){
        return $this-> hasMany(Tasks::class,'project_id');
    }
}
