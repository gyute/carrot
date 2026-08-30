<?php

use App\Enums\ToolRequestStatus;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('status')->default(ToolRequestStatus::Open->value)->index();
            $table->string('title');
            $table->text('body');
            // Stamped from the requester's own department: it decides who else
            // can see the request, so it is not theirs to pick.
            $table->string('department')->nullable()->index();
            $table->jsonb('categories')->default('[]');
            $table->string('desired_kind')->nullable();
            $table->date('needed_by')->nullable();
            $table->string('priority')->nullable();
            $table->foreignIdFor(User::class, 'assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            // A purged tool must not take the request's history with it.
            $table->foreignIdFor(Tool::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('tool_requests')->nullOnDelete();
            $table->timestamps();
        });

        // The one seam between a request and the tool that answers it:
        // approving such a submission delivers the request.
        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->foreignIdFor(ToolRequest::class)->nullable()->after('tool_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tool_request_id');
        });

        Schema::dropIfExists('tool_requests');
    }
};
