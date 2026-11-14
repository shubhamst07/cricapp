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
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('ground_id')->nullable()->after('venue')->constrained('grounds')->nullOnDelete();
            $table->foreignId('umpire_id')->nullable()->after('ground_id')->constrained('umpire_profiles')->nullOnDelete();
            $table->string('umpire_name')->nullable()->after('umpire_id');
            $table->decimal('umpire_rating', 3, 1)->nullable()->after('umpire_name');
        });

        Schema::table('grounds', function (Blueprint $table) {
            $table->unsignedInteger('matches_hosted')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ground_id');
            $table->dropConstrainedForeignId('umpire_id');
            $table->dropColumn(['umpire_name', 'umpire_rating']);
        });

        Schema::table('grounds', function (Blueprint $table) {
            $table->dropColumn('matches_hosted');
        });
    }
};
