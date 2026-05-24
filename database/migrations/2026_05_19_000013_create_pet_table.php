<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet', function (Blueprint $table) {
            $table->id('pet_id');
            $table->bigInteger('customer_id');
            $table->string('pet_name', 100);
            $table->string('species', 20);
            $table->string('breed', 50)->nullable();
            $table->string('sex', 10)->nullable();
            $table->integer('age')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('special_notes')->nullable();
            $table->timestamps();
            $table->index('species', 'idx_pet_species');
            $table->foreign('customer_id', 'fk_pet_customer')->references('customer_id')->on('customer')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE pet ADD CONSTRAINT chk_pet_age CHECK (age IS NULL OR age >= 0)');
        DB::statement('ALTER TABLE pet ADD CONSTRAINT chk_pet_weight CHECK (weight_kg IS NULL OR weight_kg > 0)');
        DB::statement("ALTER TABLE pet ADD CONSTRAINT ck_pet_species CHECK (species IN ('DOG','CAT','BIRD','RABBIT','OTHER'))");
        DB::statement("ALTER TABLE pet ADD CONSTRAINT ck_pet_sex CHECK (sex IS NULL OR sex IN ('MALE','FEMALE','UNKNOWN'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pet');
    }
};
