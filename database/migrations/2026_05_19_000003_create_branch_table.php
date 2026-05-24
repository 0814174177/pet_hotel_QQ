<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch', function (Blueprint $table) {
            $table->id('branch_id');
            $table->string('branch_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('address', 255);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->unique('email', 'uq_branch_email');
        });

        DB::statement('ALTER TABLE branch ADD CONSTRAINT ck_branch_active CHECK (is_active IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('branch');
    }
};
