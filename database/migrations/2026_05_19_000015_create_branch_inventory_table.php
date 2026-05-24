<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_inventory', function (Blueprint $table) {
            $table->id('branch_inventory_id');
            $table->bigInteger('branch_id');
            $table->bigInteger('product_id');
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('reorder_point')->nullable()->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();
            $table->unique(['branch_id', 'product_id'], 'uq_bi_branch_product');
            $table->foreign('branch_id', 'fk_bi_branch')->references('branch_id')->on('branch');
            $table->foreign('product_id', 'fk_bi_product')->references('product_id')->on('product');
        });
        DB::statement('ALTER TABLE branch_inventory ADD CONSTRAINT chk_bi_quantity CHECK (quantity_in_stock >= 0)');
        DB::statement('ALTER TABLE branch_inventory ADD CONSTRAINT chk_bi_reorder CHECK (reorder_point IS NULL OR reorder_point >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_inventory');
    }
};
