<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCouponLog extends Model
{
    protected $table = 'booking_coupon_log';
    protected $primaryKey = 'booking_coupon_log_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'coupon_id');
    }
}
