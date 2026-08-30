<?php

use App\Enums\ToolStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('slug')->unique();
            $table->string('kind');
            $table->string('name');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('icon')->default('wrench');
            $table->string('accent')->default('slate');
            $table->string('status')->default(ToolStatus::Running->value)->index();
            $table->foreignIdFor(User::class, 'owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department')->nullable();
            $table->jsonb('config');
            $table->text('source')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('version', 16)->nullable();
            $table->foreignIdFor(User::class, 'requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('value');
            $table->timestamps();

            $table->unique(['group', 'value']);
        });

        Schema::create('tag_tool', function (Blueprint $table) {
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['tool_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_tool');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tools');
    }
};
