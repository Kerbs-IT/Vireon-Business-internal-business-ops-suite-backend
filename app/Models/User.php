<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'middle_name',
        'role_id',
        'department_id',
        'position',
        'date_hired',
        'status'
    ];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_hired' => 'date'
        ];
    }

    public function role()
    {
        return $this->belongsTo(Roles::class, 'role_id', 'id');
    }
    // function to check if the user has a role
    public function hasRole(string ...$roles):bool{
        return in_array($this->role->name, $roles);
    }
    public function department()
    {
        return $this->belongsTo(Departments::class, 'department_id', 'id');
    }

    // this is for the user whose have many handled department
    public function department_managed()
    {
        return $this->hasMany(Departments::class, 'manager_id', 'id');
    }

    // fetch function for the user to get the info about the project currently under
    public function projects()
    {
        return $this->belongsToMany(Projects::class, 'project_members', 'user_id', 'project_id');
    }
}
