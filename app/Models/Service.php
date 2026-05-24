<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'service_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(CategoryService::class, 'service_category_id', 'service_category_id');
    }

    public function serviceProductDetails()
    {
        return $this->hasMany(ServiceProductDetail::class, 'service_id', 'service_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'service_product_detail', 'service_id', 'product_id', 'service_id', 'product_id')
            ->withPivot(['service_product_detail_id', 'amount', 'notes']);
    }

    public function bookingServicePets()
    {
        return $this->hasMany(BookingServicePet::class, 'service_id', 'service_id');
    }
}
