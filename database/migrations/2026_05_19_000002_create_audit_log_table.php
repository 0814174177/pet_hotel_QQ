<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id('audit_id');
            $table->string('table_name', 128);
            $table->string('action_type', 20);
            $table->string('row_pk', 255);
            $table->text('detail_text')->nullable();
            $table->bigInteger('changed_by_user_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['table_name', 'changed_at'], 'idx_audit_table_time');
            $table->foreign('changed_by_user_id', 'fk_audit_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT ck_audit_action CHECK (action_type IN ('INSERT','UPDATE','DELETE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
