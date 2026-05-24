<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id('service_id');
            $table->bigInteger('service_category_id');
            $table->string('service_name', 120);
            $table->string('species', 20)->default('ALL');
            $table->text('description_sv')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->integer('duration_minutes')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index('service_name', 'idx_services_name');
            $table->index('species', 'idx_services_species');
            $table->index('is_active', 'idx_services_active');

            $table->foreign('service_category_id', 'fk_services_category')
                ->references('service_category_id')
                ->on('category_services');
        });

        DB::statement("ALTER TABLE services ADD CONSTRAINT ck_services_species CHECK (species IN ('ALL','DOG','CAT','BIRD','RABBIT','OTHER'))");
        DB::statement('ALTER TABLE services ADD CONSTRAINT ck_services_price CHECK (base_price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT ck_services_duration CHECK (duration_minutes IS NULL OR duration_minutes > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT ck_services_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};