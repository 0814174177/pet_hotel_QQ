<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->bigInteger('customer_id');
            $table->bigInteger('branch_id');
            $table->bigInteger('booking_id')->nullable();
            $table->bigInteger('created_by_emp')->nullable();
            $table->bigInteger('created_by_user_id');
            $table->bigInteger('coupon_id')->nullable();
            $table->string('payment_method', 30);
            $table->string('status', 20)->default('PENDING');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->index('status', 'idx_orders_status');
            $table->foreign('customer_id', 'fk_orders_customer')->references('customer_id')->on('customer');
            $table->foreign('branch_id', 'fk_orders_branch')->references('branch_id')->on('branch');
            $table->foreign('booking_id', 'fk_orders_booking')->references('booking_id')->on('booking');
            $table->foreign('created_by_emp', 'fk_orders_employee')->references('employee_id')->on('employee');
            $table->foreign('created_by_user_id', 'fk_orders_user')->references('id')->on('users');
            $table->foreign('coupon_id', 'fk_orders_coupon')->references('coupon_id')->on('coupon')->nullOnDelete();
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT ck_orders_method CHECK (payment_method IN ('CASH','BANK_TRANSFER','MOMO','VNPAY','ZALOPAY','CARD','EWALLET','OTHER'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT ck_orders_status CHECK (status IN ('PENDING','PROCESSING','COMPLETED','PAID','PARTIAL','CANCELLED','REFUNDED'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_subtotal CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_discount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_grand_total CHECK (grand_total >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_grand_calc CHECK (grand_total = subtotal - discount_amount)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_paid_at CHECK ((status IN (\'COMPLETED\', \'PAID\') AND paid_at IS NOT NULL) OR status NOT IN (\'COMPLETED\', \'PAID\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
