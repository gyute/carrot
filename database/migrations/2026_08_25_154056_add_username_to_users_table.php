<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        $this->backfillUsernames();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Give every existing account a username derived from its email address.
     */
    private function backfillUsernames(): void
    {
        $taken = [];

        DB::table('users')->orderBy('id')->select('id', 'email')->each(function (object $user) use (&$taken): void {
            $base = Str::of($user->email)->before('@')->lower()->replaceMatches('/[^a-z0-9_-]/', '')->limit(50, '')->value();
            $base = $base === '' ? 'user' : $base;

            $username = $base;
            $suffix = 1;

            while (in_array($username, $taken, true)) {
                $username = $base.(++$suffix);
            }

            $taken[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }
};
