<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'product';
    protected $primaryKey = 'product_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(CategoryProduct::class, 'product_category_id', 'product_category_id');
    }

    public function serviceProductDetails()
    {
        return $this->hasMany(ServiceProductDetail::class, 'product_id', 'product_id');
    }

    public function inventories()
    {
        return $this->hasMany(BranchInventory::class, 'product_id', 'product_id');
    }
}
