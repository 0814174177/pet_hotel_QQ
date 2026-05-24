<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeRoom extends Model
{
    protected $table = 'type_room';
    protected $primaryKey = 'type_room_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function rooms()
    {
        return $this->hasMany(Room::class, 'type_room_id', 'type_room_id');
    }
}
