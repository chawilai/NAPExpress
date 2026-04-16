<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autonap_records', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('created_at');
            $table->index('expires_at');
        });

        // Backfill existing rows — expire 90 days after creation (MySQL in production, SQLite in tests)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                'UPDATE autonap_records SET expires_at = DATE_ADD(created_at, INTERVAL 90 DAY) WHERE expires_at IS NULL'
            );
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "UPDATE autonap_records SET expires_at = datetime(created_at, '+90 days') WHERE expires_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::table('autonap_records', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
