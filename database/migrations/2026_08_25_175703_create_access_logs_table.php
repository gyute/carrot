<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stand-in for the access log the real portal would collect. It exists so the
 * export tool has something realistic to read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('ip_address', 45);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('accessed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
