<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer', function (Blueprint $table) {
            $table->id('customer_id');
            $table->bigInteger('user_id');
            $table->string('full_name', 100);
            $table->string('phone', 20);
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('user_id', 'uq_customer_user');
            $table->unique('phone', 'uq_customer_phone');
            $table->foreign('user_id', 'fk_customer_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer');
    }
};
