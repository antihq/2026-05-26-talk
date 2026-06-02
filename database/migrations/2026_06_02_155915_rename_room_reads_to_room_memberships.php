<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('room_reads', 'room_memberships');

        Schema::table('room_memberships', function (Blueprint $table) {
            $table->integer('connections')->default(0)->after('last_read_at');
            $table->timestamp('connected_at')->nullable()->after('connections');
        });
    }

    public function down(): void
    {
        Schema::table('room_memberships', function (Blueprint $table) {
            $table->dropColumn(['connections', 'connected_at']);
        });

        Schema::rename('room_memberships', 'room_reads');
    }
};
