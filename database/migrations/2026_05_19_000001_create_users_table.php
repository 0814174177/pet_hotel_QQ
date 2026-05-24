<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('role', 20)->default('CUSTOMER');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unique('email', 'uq_users_email');
            $table->index('role', 'idx_users_role');
            $table->index('is_active', 'idx_users_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('user_id', 'idx_sessions_user');
            $table->index('last_activity', 'idx_sessions_activity');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('CUSTOMER','RECEPTIONIST','GROOMER','MANAGER','ADMIN'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT ck_users_active CHECK (is_active IN (0,1))');
    }

    

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
