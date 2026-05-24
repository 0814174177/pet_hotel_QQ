<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoom extends Model
{
    protected $table = 'booking_room';
    protected $primaryKey = 'booking_room_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    public function bookingRoomPets()
    {
        return $this->hasMany(BookingRoomPet::class, 'booking_room_id', 'booking_room_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'booking_room_id', 'booking_room_id');
    }
}
