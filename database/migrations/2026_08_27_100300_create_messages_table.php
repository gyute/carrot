<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignIdFor(User::class, 'recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('subject');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
