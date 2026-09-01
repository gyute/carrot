<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog filter a person chose to keep, as {group: [value, ...]}. Null
 * means they never saved one, which is not the same as saving an empty filter:
 * one gets the default, the other asked to see everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('catalog_filters')->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('catalog_filters');
        });
    }
};
