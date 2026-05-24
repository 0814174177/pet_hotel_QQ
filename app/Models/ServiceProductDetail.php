<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProductDetail extends Model
{
    protected $table = 'service_product_detail';
    protected $primaryKey = 'service_product_detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
