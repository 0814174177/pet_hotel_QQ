<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('orders')->insert([
            [
                'order_id' => 1,
                'customer_id' => 1,
                'branch_id' => 1,
                'booking_id' => 1,
                'created_by_emp' => 1,
                'created_by_user_id' => 2,
                'coupon_id' => null,
                'payment_method' => 'CASH',
                'status' => 'PENDING',
                'subtotal' => 0,
                'discount_amount' => 0,
                'grand_total' => 0,
                'paid_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => 2,
                'customer_id' => 2,
                'branch_id' => 1,
                'booking_id' => 2,
                'created_by_emp' => 1,
                'created_by_user_id' => 2,
                'coupon_id' => null,
                'payment_method' => 'BANK_TRANSFER',
                'status' => 'PENDING',
                'subtotal' => 0,
                'discount_amount' => 0,
                'grand_total' => 0,
                'paid_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
         * Oracle trigger work is deferred, so the seeder writes totals
         * explicitly and keeps line_total compatible with the CHECK rules.
         */
        DB::table('order_details')->insert([
            [
                'order_detail_id' => 1,
                'order_id' => 1,
                'booking_room_id' => 1,
                'booking_service_pet_id' => null,
                'title' => 'Lưu trú phòng nhỏ 2 ngày',
                'quantity' => 2,
                'unit_price' => 150000,
                'line_total' => 300000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_detail_id' => 2,
                'order_id' => 1,
                'booking_room_id' => null,
                'booking_service_pet_id' => 1,
                'title' => 'Tắm sấy cơ bản cho Milo',
                'quantity' => 1,
                'unit_price' => 120000,
                'line_total' => 120000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_detail_id' => 3,
                'order_id' => 2,
                'booking_room_id' => 2,
                'booking_service_pet_id' => null,
                'title' => 'Lưu trú phòng vừa 2 ngày',
                'quantity' => 2,
                'unit_price' => 220000,
                'line_total' => 440000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_detail_id' => 4,
                'order_id' => 2,
                'booking_room_id' => null,
                'booking_service_pet_id' => 2,
                'title' => 'Kiểm tra sức khỏe cơ bản cho Ben',
                'quantity' => 1,
                'unit_price' => 80000,
                'line_total' => 80000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
         * Sau khi order_details chạy xong, order 1 có subtotal = 420000.
         * Áp coupon WELCOME50: grand_total = 420000 - 50000 = 370000.
         */
        DB::table('orders')
            ->where('order_id', 1)
            ->update([
                'coupon_id' => 1,
                'subtotal' => 420000,
                'discount_amount' => 50000,
                'grand_total' => 370000,
                'updated_at' => $now,
            ]);

        DB::table('orders')
            ->where('order_id', 2)
            ->update([
                'subtotal' => 520000,
                'grand_total' => 520000,
                'updated_at' => $now,
            ]);

        DB::table('booking_coupon_log')->insert([
            [
                'booking_coupon_log_id' => 1,
                'booking_id' => 1,
                'coupon_id' => 1,
                'applied_at' => $now,
                'notes' => 'Áp dụng coupon WELCOME50 cho booking 1.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('booking')->where('booking_id', 1)->update([
            'total_amount' => 370000,
            'updated_at' => $now,
        ]);

        DB::table('booking')->where('booking_id', 2)->update([
            'total_amount' => 520000,
            'updated_at' => $now,
        ]);

        /*
         * The paid order needs paid_at because the Oracle CHECK constraint
         * requires a payment timestamp for COMPLETED/PAID statuses.
         */
        DB::table('orders')->where('order_id', 1)->update([
            'status' => 'COMPLETED',
            'paid_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('payments')->insert([
            [
                'payment_id' => 1,
                'order_id' => 1,
                'payment_method' => 'CASH',
                'provider' => null,
                'amount' => 370000,
                'status' => 'SUCCESS',
                'paid_at' => $now,
                'note' => 'Thanh toan mau cho order 1.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
