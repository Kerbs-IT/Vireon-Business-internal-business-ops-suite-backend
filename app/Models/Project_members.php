<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project_members extends Model
{
    //
    protected $fillable = [
        'project_id',
        'user_id',
    
    ];

    // public function project(){
    //     return $this -> belongsTo(Projects::class,'project_id','id');
    // }

    // public function user(){
    //     return $this -> belongsTo(User::class,'user_id','id');
    // }
}
