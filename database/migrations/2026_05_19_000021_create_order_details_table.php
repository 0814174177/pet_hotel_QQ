<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id('order_detail_id');

            $table->bigInteger('order_id');

            $table->bigInteger('booking_room_id')->nullable();
            $table->bigInteger('booking_service_pet_id')->nullable();

            $table->string('title', 200);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->foreign('order_id', 'fk_od_order')
                ->references('order_id')
                ->on('orders')
                ->cascadeOnDelete();

            // Không dùng cascadeOnUpdate() / nullOnDelete()
            // vì 2 cột này đang được dùng trong CHECK constraint
            $table->foreign('booking_room_id', 'fk_od_booking_room')
                ->references('booking_room_id')
                ->on('booking_room');

            $table->foreign('booking_service_pet_id', 'fk_od_bsp')
                ->references('booking_service_pet_id')
                ->on('booking_service_pet');
        });

        DB::statement('ALTER TABLE order_details ADD CONSTRAINT chk_od_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_details ADD CONSTRAINT chk_od_unit_price CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_details ADD CONSTRAINT chk_od_line_total CHECK (line_total >= 0)');
        DB::statement('ALTER TABLE order_details ADD CONSTRAINT chk_od_line_calc CHECK (line_total = quantity * unit_price)');

        DB::statement('ALTER TABLE order_details ADD CONSTRAINT chk_od_has_ref CHECK (
            booking_room_id IS NOT NULL OR booking_service_pet_id IS NOT NULL
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
