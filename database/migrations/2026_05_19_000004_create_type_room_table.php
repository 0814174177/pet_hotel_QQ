<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_room', function (Blueprint $table) {
            $table->id('type_room_id');
            $table->string('type_name', 100);
            $table->integer('max_slot');
            $table->decimal('pet_weight_min_kg', 5, 2)->nullable();
            $table->decimal('pet_weight_max_kg', 5, 2)->nullable();
            $table->decimal('base_price_per_day', 10, 2);
            $table->text('notes')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE type_room ADD CONSTRAINT chk_type_room_max_slot CHECK (max_slot > 0)');
        DB::statement('ALTER TABLE type_room ADD CONSTRAINT chk_type_room_base_price CHECK (base_price_per_day >= 0)');
        DB::statement('ALTER TABLE type_room ADD CONSTRAINT chk_type_room_weight_range CHECK (pet_weight_min_kg IS NULL OR pet_weight_max_kg IS NULL OR pet_weight_max_kg >= pet_weight_min_kg)');
        DB::statement('ALTER TABLE type_room ADD CONSTRAINT ck_type_room_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('type_room');
    }
};
