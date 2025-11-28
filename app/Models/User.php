<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    protected $fillable = [
        'f_name',
        'l_name',
        'phone_number',
        'password',
        'role_id',
        'last_login_at',
        'otp',
        'otp_expires_at',
        'fcm_token',
    ];


    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function citizen() {
        return $this->hasOne(Citizen::class);
    }

    public function employee() {
        return $this->hasOne(Employee::class);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];


}
