<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_services', function (Blueprint $table) {
            $table->id('service_category_id');
            $table->string('service_category_name', 100);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->unique('service_category_name', 'uq_cat_service_name');
        });

        DB::statement('ALTER TABLE category_services ADD CONSTRAINT ck_cat_service_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('category_services');
    }
};
