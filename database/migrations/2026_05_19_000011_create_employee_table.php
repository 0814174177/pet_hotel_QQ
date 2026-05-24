<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee', function (Blueprint $table) {
            $table->id('employee_id');
            $table->bigInteger('user_id');
            $table->bigInteger('branch_id');
            $table->string('full_name', 200);
            $table->string('position', 20);
            $table->decimal('salary', 12, 2)->default(0);
            $table->string('phone', 20)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('experience', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('user_id', 'uq_employee_user');
            $table->foreign('user_id', 'fk_employee_user')->references('id')->on('users');
            $table->foreign('branch_id', 'fk_employee_branch')->references('branch_id')->on('branch');
        });
        DB::statement('ALTER TABLE employee ADD CONSTRAINT chk_employee_salary CHECK (salary >= 0)');
        DB::statement("ALTER TABLE employee ADD CONSTRAINT ck_employee_position CHECK (position IN ('GROOMER','RECEPTIONIST','MANAGER','VET','CLEANER','OTHER'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee');
    }
};
