<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The framework's notifications table stores `data` as text, so Postgres
 * refuses `data->>'message'` with "operator does not exist: text ->> unknown".
 * SQLite has no column types to speak of and reads JSON out of text happily,
 * which is why the test suite never saw this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
