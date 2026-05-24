<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id', 'id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'changed_by_user_id', 'id');
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by_user_id', 'id');
    }

    public function isCustomer(): bool
    {
        return $this->role === 'CUSTOMER';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isManager(): bool
    {
        return $this->role === 'MANAGER';
    }

    public function isReceptionist(): bool
    {
        return $this->role === 'RECEPTIONIST';
    }

    public function isGroomer(): bool
    {
        return $this->role === 'GROOMER';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [
            'ADMIN',
            'MANAGER',
            'RECEPTIONIST',
            'GROOMER',
        ]);
    }
}
