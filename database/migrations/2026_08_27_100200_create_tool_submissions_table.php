<?php

use App\Enums\SubmissionStatus;
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
        Schema::create('tool_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Tool::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('status')->default(SubmissionStatus::Draft->value)->index();
            $table->jsonb('payload');
            $table->text('note')->nullable();
            $table->foreignIdFor(User::class, 'reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignIdFor(ToolSubmission::class, 'approved_submission_id')
                ->nullable()
                ->after('approved_by')
                ->constrained('tool_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_submission_id');
        });

        Schema::dropIfExists('tool_submissions');
    }
};
