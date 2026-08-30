<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->foreignIdFor(User::class, 'endorsed_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            $table->text('endorse_comment')->nullable()->after('endorsed_by');
            $table->timestamp('endorsed_at')->nullable()->after('endorse_comment');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignIdFor(User::class, 'endorsed_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('endorsed_by');
        });

        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('endorsed_by');
            $table->dropColumn(['endorse_comment', 'endorsed_at']);
        });
    }
};
