<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What ties a row to the repository it is mirrored in: the pull request a
 * submission is being reviewed as, and the commit a published version became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->unsignedInteger('github_pr_number')->nullable()->after('reviewed_at');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->string('mirror_commit_sha', 40)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('tool_submissions', function (Blueprint $table) {
            $table->dropColumn('github_pr_number');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('mirror_commit_sha');
        });
    }
};
