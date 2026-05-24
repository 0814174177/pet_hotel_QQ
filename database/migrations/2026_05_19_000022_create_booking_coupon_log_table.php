<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_coupon_log', function (Blueprint $table) {
            $table->id('booking_coupon_log_id');
            $table->bigInteger('booking_id');
            $table->bigInteger('coupon_id');
            $table->timestamp('applied_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'coupon_id', 'applied_at'], 'uq_bcl_booking_coupon_time');
            $table->foreign('booking_id', 'fk_bcl_booking')->references('booking_id')->on('booking')->cascadeOnDelete();
            $table->foreign('coupon_id', 'fk_bcl_coupon')->references('coupon_id')->on('coupon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_coupon_log');
    }
};
