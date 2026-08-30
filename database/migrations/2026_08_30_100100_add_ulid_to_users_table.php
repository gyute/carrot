<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Users get the same public handle every other addressable row already has.
 * The login ID is not one: an external identity provider owns it once SSO is
 * in place, so anything that points at a person must not be spelled with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid()->nullable()->after('id');
        });

        $this->backfillUlids();

        Schema::table('users', function (Blueprint $table) {
            $table->char('ulid', 26)->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }

    private function backfillUlids(): void
    {
        DB::table('users')->orderBy('id')->select('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['ulid' => (string) Str::ulid()]);
        });
    }
};
