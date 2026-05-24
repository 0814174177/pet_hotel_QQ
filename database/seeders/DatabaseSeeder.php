<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CleanupSeeder::class,
            LaravelSystemSeeder::class,
            UserSeeder::class,
            AuthSupportSeeder::class,
            BranchRoomSeeder::class,
            ProductServiceSeeder::class,
            PeoplePetSeeder::class,
            CouponInventorySeeder::class,
            BookingSeeder::class,
            OrderSeeder::class,
            AuditLogSeeder::class,
        ]);

        $this->syncOracleAutoIncrementSequences();
    }

    private function syncOracleAutoIncrementSequences(): void
    {
        if (DB::getDriverName() !== 'oracle') {
            return;
        }

        $primaryKeys = [
            'users' => 'id',
            'jobs' => 'id',
            'failed_jobs' => 'id',
            'audit_log' => 'audit_id',
            'branch' => 'branch_id',
            'type_room' => 'type_room_id',
            'room' => 'room_id',
            'category_product' => 'product_category_id',
            'category_services' => 'service_category_id',
            'product' => 'product_id',
            'services' => 'service_id',
            'service_product_detail' => 'service_product_detail_id',
            'employee' => 'employee_id',
            'customer' => 'customer_id',
            'pet' => 'pet_id',
            'coupon' => 'coupon_id',
            'branch_inventory' => 'branch_inventory_id',
            'booking' => 'booking_id',
            'booking_room' => 'booking_room_id',
            'booking_room_pet' => 'booking_room_pet_id',
            'booking_service_pet' => 'booking_service_pet_id',
            'orders' => 'order_id',
            'order_details' => 'order_detail_id',
            'booking_coupon_log' => 'booking_coupon_log_id',
            'payments' => 'payment_id',
        ];

        foreach ($primaryKeys as $table => $column) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $maxId = (int) DB::table($table)->max($column);
            $target = $maxId + 1;

            if ($target <= 1) {
                continue;
            }

            $this->advanceOracleSequence($table, $column, $target);
        }
    }

    private function advanceOracleSequence(string $table, string $column, int $target): void
    {
        $sequence = $this->oracleSequenceName($table, $column);

        try {
            $currentRow = DB::selectOne("select {$sequence}.nextval as value from dual");
            $current = (int) ($currentRow->value ?? 0);

            if ($current >= $target) {
                return;
            }

            DB::statement('alter sequence '.$sequence.' increment by '.($target - $current));
            DB::selectOne("select {$sequence}.nextval as value from dual");
            DB::statement('alter sequence '.$sequence.' increment by 1');
        } catch (Throwable) {
            // Identity-column Oracle setups may not create the named sequence.
        }
    }

    private function oracleSequenceName(string $table, string $column): string
    {
        $maxLength = (int) config('database.connections.oracle.max_name_len', 30);

        return substr($table.'_'.$column.'_seq', 0, $maxLength);
    }
}
