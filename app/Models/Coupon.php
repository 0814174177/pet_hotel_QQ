<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupon';
    protected $primaryKey = 'coupon_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'coupon_id', 'coupon_id');
    }

    public function bookingCouponLogs()
    {
        return $this->hasMany(BookingCouponLog::class, 'coupon_id', 'coupon_id');
    }
}
