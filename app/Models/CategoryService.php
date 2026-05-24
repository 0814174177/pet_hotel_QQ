<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryService extends Model
{
    protected $table = 'category_services';
    protected $primaryKey = 'service_category_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $guarded = [];

    public function services()
    {
        return $this->hasMany(Service::class, 'service_category_id', 'service_category_id');
    }
}
