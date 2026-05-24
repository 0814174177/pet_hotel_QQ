<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon', function (Blueprint $table) {
            $table->id('coupon_id');
            $table->string('coupon_code', 50);
            $table->bigInteger('employee_id')->nullable();
            $table->string('discount_type', 20)->default('FIXED');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order_value', 12, 2)->default(0);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->dateTime('effective_from');
            $table->dateTime('expired_at');
            $table->tinyInteger('is_active')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('coupon_code', 'uq_coupon_code');
            $table->index(['is_active', 'effective_from', 'expired_at'], 'idx_coupon_active_dates');
            $table->foreign('employee_id', 'fk_coupon_employee')->references('employee_id')->on('employee')->nullOnDelete();
        });
        DB::statement("ALTER TABLE coupon ADD CONSTRAINT ck_coupon_type CHECK (discount_type IN ('FIXED','PERCENT'))");
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_value CHECK (discount_value > 0)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_percent CHECK (discount_type != \'PERCENT\' OR discount_value <= 100)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_min_order CHECK (min_order_value >= 0)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_max_uses CHECK (max_uses IS NULL OR max_uses > 0)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_used_count CHECK (used_count >= 0)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT chk_coupon_date_range CHECK (expired_at > effective_from)');
        DB::statement('ALTER TABLE coupon ADD CONSTRAINT ck_coupon_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon');
    }
};
