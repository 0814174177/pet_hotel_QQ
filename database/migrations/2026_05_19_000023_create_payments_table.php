<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->bigInteger('order_id');
            $table->string('payment_method', 30);
            $table->string('provider', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('PENDING');
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('order_id', 'uq_payments_order');
            $table->index('status', 'idx_payments_status');
            $table->foreign('order_id', 'fk_payments_order')
                ->references('order_id')
                ->on('orders')
                ->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT ck_payments_method CHECK (payment_method IN ('CASH','BANK_TRANSFER','CARD','EWALLET','MOMO','VNPAY','ZALOPAY','OTHER'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT ck_payments_status CHECK (status IN ('PENDING','SUCCESS','FAILED','REFUNDED'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT ck_payments_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT ck_payments_paid_at CHECK ((status IN ('SUCCESS','REFUNDED') AND paid_at IS NOT NULL) OR (status IN ('PENDING','FAILED') AND paid_at IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
