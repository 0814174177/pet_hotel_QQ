<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoomPet extends Model
{
    protected $table = 'booking_room_pet';
    protected $primaryKey = 'booking_room_pet_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class, 'booking_room_id', 'booking_room_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class, 'pet_id', 'pet_id');
    }
}
