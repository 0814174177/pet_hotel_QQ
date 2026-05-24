<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'booking';
    protected $primaryKey = 'booking_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class, 'booking_id', 'booking_id');
    }

    public function bookingServicePets()
    {
        return $this->hasMany(BookingServicePet::class, 'booking_id', 'booking_id');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_room', 'booking_id', 'room_id', 'booking_id', 'room_id')
            ->withPivot(['booking_room_id', 'assigned_at', 'notes']);
    }

    public function bookingServicesPet()
    {
        return $this->bookingServicePets();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'booking_id', 'booking_id');
    }

    public function couponLogs()
    {
        return $this->hasMany(BookingCouponLog::class, 'booking_id', 'booking_id');
    }
}
