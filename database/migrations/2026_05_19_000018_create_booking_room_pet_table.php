<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_room_pet', function (Blueprint $table) {
            $table->id('booking_room_pet_id');
            $table->bigInteger('booking_room_id');
            $table->bigInteger('pet_id');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['booking_room_id', 'pet_id'], 'uq_brp_room_pet');
            $table->foreign('booking_room_id', 'fk_brp_booking_room')->references('booking_room_id')->on('booking_room')->cascadeOnDelete();
            $table->foreign('pet_id', 'fk_brp_pet')->references('pet_id')->on('pet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_pet');
    }
};
