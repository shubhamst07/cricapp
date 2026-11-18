<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('match_meetups') && !Schema::hasColumn('match_meetups', 'google_maps_url')) {
            Schema::table('match_meetups', function (Blueprint $table) {
                $table->string('google_maps_url', 500)->nullable()->after('location_address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('match_meetups') && Schema::hasColumn('match_meetups', 'google_maps_url')) {
            Schema::table('match_meetups', function (Blueprint $table) {
                $table->dropColumn('google_maps_url');
            });
        }
    }
};
