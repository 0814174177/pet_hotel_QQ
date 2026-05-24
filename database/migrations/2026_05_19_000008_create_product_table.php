<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->id('product_id');
            $table->bigInteger('product_category_id');
            $table->string('product_image_url', 255)->nullable();
            $table->string('product_name', 255);
            $table->string('unit', 20)->nullable();
            $table->decimal('item_price', 12, 2);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index('product_name', 'idx_product_name');

            $table->foreign('product_category_id', 'fk_product_category')
                ->references('product_category_id')
                ->on('category_product');
        });

        DB::statement('ALTER TABLE product ADD CONSTRAINT chk_product_price CHECK (item_price >= 0)');
        DB::statement('ALTER TABLE product ADD CONSTRAINT ck_product_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};