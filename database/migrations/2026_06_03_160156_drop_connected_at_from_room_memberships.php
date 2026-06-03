<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_memberships', function (Blueprint $table) {
            $table->dropColumn('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('room_memberships', function (Blueprint $table) {
            $table->timestamp('connected_at')->nullable()->after('last_read_at');
        });
    }
};
