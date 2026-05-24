<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_booking_sync_room_status');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_booking_sync_room_status
AFTER UPDATE ON booking
FOR EACH ROW
BEGIN
    IF NEW.status = 'CHECKED_IN'
       AND OLD.status <> 'CHECKED_IN'
       AND NEW.checkin_actual_at IS NOT NULL
    THEN
        UPDATE room
        JOIN booking_room ON booking_room.room_id = room.room_id
        SET room.status = 'IN_USE'
        WHERE booking_room.booking_id = NEW.booking_id
          AND room.status <> 'MAINTENANCE';
    END IF;

    IF NEW.status IN ('CHECKED_OUT', 'COMPLETED')
       AND OLD.status NOT IN ('CHECKED_OUT', 'COMPLETED')
       AND NEW.checkout_actual_at IS NOT NULL
    THEN
        UPDATE room
        JOIN booking_room ON booking_room.room_id = room.room_id
        SET room.status = 'AVAILABLE'
        WHERE booking_room.booking_id = NEW.booking_id
          AND room.status <> 'MAINTENANCE';
    END IF;

    IF NEW.status = 'CANCELLED'
       AND OLD.status <> 'CANCELLED'
    THEN
        UPDATE room
        JOIN booking_room ON booking_room.room_id = room.room_id
        SET room.status = 'AVAILABLE'
        WHERE booking_room.booking_id = NEW.booking_id
          AND room.status <> 'MAINTENANCE';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_booking_sync_room_status');
    }
};
