<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog was briefly mirrored to a git repository, with each submission
 * proposed as a pull request. Guarded because a database created after the
 * mirror was removed never had these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tool_submissions', 'github_pr_number')) {
            Schema::table('tool_submissions', function (Blueprint $table) {
                $table->dropColumn('github_pr_number');
            });
        }

        if (Schema::hasColumn('tools', 'mirror_commit_sha')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->dropColumn('mirror_commit_sha');
            });
        }
    }

    public function down(): void
    {
        // The mirror is gone; there is nothing to put back.
    }
};
