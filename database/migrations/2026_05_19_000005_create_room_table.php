<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room', function (Blueprint $table) {
            $table->id('room_id');
            $table->bigInteger('branch_id');
            $table->bigInteger('type_room_id');
            $table->string('room_number', 20);
            $table->string('status', 20)->default('AVAILABLE');
            $table->timestamps();

            $table->unique(['branch_id', 'room_number'], 'uq_room_branch_no');
            $table->index('type_room_id', 'idx_room_type');
            $table->index('status', 'idx_room_status');

            $table->foreign('branch_id', 'fk_room_branch')
                ->references('branch_id')
                ->on('branch');

            $table->foreign('type_room_id', 'fk_room_type')
                ->references('type_room_id')
                ->on('type_room');
        });

        DB::statement("ALTER TABLE room ADD CONSTRAINT ck_room_status CHECK (status IN ('AVAILABLE','IN_USE','MAINTENANCE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('room');
    }
};