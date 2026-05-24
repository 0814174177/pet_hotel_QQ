<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $table = 'order_details';
    protected $primaryKey = 'order_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class, 'booking_room_id', 'booking_room_id');
    }

    public function bookingServicePet()
    {
        return $this->belongsTo(BookingServicePet::class, 'booking_service_pet_id', 'booking_service_pet_id');
    }
}
