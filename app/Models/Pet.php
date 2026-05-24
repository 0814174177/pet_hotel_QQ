<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    protected $table = 'pet';
    protected $primaryKey = 'pet_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function bookingRoomPets()
    {
        return $this->hasMany(BookingRoomPet::class, 'pet_id', 'pet_id');
    }

    public function bookingServicePets()
    {
        return $this->hasMany(BookingServicePet::class, 'pet_id', 'pet_id');
    }
}
