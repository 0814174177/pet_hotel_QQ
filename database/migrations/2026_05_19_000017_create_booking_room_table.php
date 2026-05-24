<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_room', function (Blueprint $table) {
            $table->id('booking_room_id');
            $table->bigInteger('booking_id');
            $table->bigInteger('room_id');
            $table->dateTime('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'room_id'], 'uq_booking_room');
            $table->foreign('booking_id', 'fk_bkr_booking')->references('booking_id')->on('booking')->cascadeOnDelete();
            $table->foreign('room_id', 'fk_bkr_room')->references('room_id')->on('room');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room');
    }
};
