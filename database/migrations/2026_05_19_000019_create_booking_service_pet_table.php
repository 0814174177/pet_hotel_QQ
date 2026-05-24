<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_service_pet', function (Blueprint $table) {
            $table->id('booking_service_pet_id');
            $table->bigInteger('booking_id');
            $table->bigInteger('pet_id');
            $table->bigInteger('service_id');
            $table->bigInteger('employee_id')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status', 'idx_bsp_status');
            $table->foreign('booking_id', 'fk_bsp_booking')->references('booking_id')->on('booking')->cascadeOnDelete();
            $table->foreign('pet_id', 'fk_bsp_pet')->references('pet_id')->on('pet');
            $table->foreign('service_id', 'fk_bsp_service')->references('service_id')->on('services');
            $table->foreign('employee_id', 'fk_bsp_employee')->references('employee_id')->on('employee')->nullOnDelete();
        });

        DB::statement("ALTER TABLE booking_service_pet ADD CONSTRAINT ck_bsp_status CHECK (status IN ('PENDING','ASSIGNED','SCHEDULED','IN_PROGRESS','DONE','CANCELLED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_service_pet');
    }
};
