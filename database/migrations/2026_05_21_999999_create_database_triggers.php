<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The previous version of this migration contained MySQL-only trigger
         * syntax. Oracle trigger, procedure, and function work is intentionally
         * deferred until table migrations, seeders, and basic web flows are
         * stable.
         */
    }

    public function down(): void
    {
        // Deferred Oracle trigger work has no rollback in this migration.
    }
};
