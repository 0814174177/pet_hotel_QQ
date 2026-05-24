<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingServicePet extends Model
{
    protected $table = 'booking_service_pet';
    protected $primaryKey = 'booking_service_pet_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class, 'pet_id', 'pet_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'booking_service_pet_id', 'booking_service_pet_id');
    }
}
