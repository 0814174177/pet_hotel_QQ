<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branch';
    protected $primaryKey = 'branch_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function rooms()
    {
        return $this->hasMany(Room::class, 'branch_id', 'branch_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id', 'branch_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'branch_id', 'branch_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'branch_id', 'branch_id');
    }

    public function inventories()
    {
        return $this->hasMany(BranchInventory::class, 'branch_id', 'branch_id');
    }
}
