<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_product_detail', function (Blueprint $table) {
            $table->id('service_product_detail_id');
            $table->bigInteger('service_id');
            $table->bigInteger('product_id');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'product_id'], 'uq_spd_service_product');
            $table->foreign('service_id', 'fk_spd_service')->references('service_id')->on('services');
            $table->foreign('product_id', 'fk_spd_product')->references('product_id')->on('product');
        });
        DB::statement('ALTER TABLE service_product_detail ADD CONSTRAINT chk_spd_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_product_detail');
    }
};
