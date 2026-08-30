<?php

use App\Enums\ToolRunStatus;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignIdFor(Tool::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ToolSubmission::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('runtime', 8);
            $table->string('source_hash', 64);
            $table->string('status')->default(ToolRunStatus::Queued->value)->index();
            $table->jsonb('inputs');
            $table->smallInteger('exit_code')->nullable();
            $table->text('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->boolean('truncated')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_runs');
    }
};
