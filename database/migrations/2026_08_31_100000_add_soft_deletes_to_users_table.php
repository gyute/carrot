<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People leave; what they registered and approved does not. Deleting the row
 * took the approval history with it - and refused outright once a tool
 * pointed at one of their submissions - so a departure retires the row
 * instead: it stays, scrubbed of the person, and every reference to it stays
 * valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
