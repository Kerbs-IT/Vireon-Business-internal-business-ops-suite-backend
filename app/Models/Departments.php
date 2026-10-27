<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departments extends Model
{
    //

    protected $fillable = [
        'name',
        'manager_id',
        
    ];

    public function manager(){
        return $this -> belongsTo(User::class,'manager_id','id');
    }

    public function users(){
        return $this -> hasMany(User::class,'department_id');
    }
}
