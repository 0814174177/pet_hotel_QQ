<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'room';
    protected $primaryKey = 'room_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function typeRoom()
    {
        return $this->belongsTo(TypeRoom::class, 'type_room_id', 'type_room_id');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class, 'room_id', 'room_id');
    }
}
