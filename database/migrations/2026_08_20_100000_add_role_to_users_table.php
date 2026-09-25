<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'sales' is the safe default for anyone already in the table —
            // it's the least-privileged role, so an existing account never
            // silently gains admin/manager access just from this migration.
            $table->string('role')->default('sales')->after('email');
        });

        // The very first account in an existing install is, in practice,
        // whoever set the project up — give them admin so there's at least
        // one account able to reach /admin/users and promote others.
        // Fresh installs get this via the seeder instead (see
        // DatabaseSeeder), so this only matters when upgrading a database
        // that already has users in it.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
