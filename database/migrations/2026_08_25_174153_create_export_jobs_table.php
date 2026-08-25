<?php

use App\Enums\ExportJobStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('definition');
            $table->string('status')->default(ExportJobStatus::Queued->value);
            $table->string('download_code', 12)->unique();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
