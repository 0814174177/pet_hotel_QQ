<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employee';
    protected $primaryKey = 'employee_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'employee_id', 'employee_id');
    }

    public function bookingServicePets()
    {
        return $this->hasMany(BookingServicePet::class, 'employee_id', 'employee_id');
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by_emp', 'employee_id');
    }
}
