<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name', 100)->nullable()->after('created_by_user_id');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
            $table->string('customer_email', 255)->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_email']);
        });
    }
};
