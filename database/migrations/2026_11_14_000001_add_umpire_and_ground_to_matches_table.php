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
        if (!Schema::hasColumn('matches', 'ground_id')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->foreignId('ground_id')->nullable()->after('venue')->constrained('grounds')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('matches', 'umpire_id')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->foreignId('umpire_id')->nullable()->constrained('umpire_profiles')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('matches', 'umpire_name')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->string('umpire_name')->nullable();
            });
        }

        if (!Schema::hasColumn('matches', 'umpire_rating')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->decimal('umpire_rating', 3, 1)->nullable();
            });
        }

        if (Schema::hasTable('grounds') && !Schema::hasColumn('grounds', 'matches_hosted')) {
            Schema::table('grounds', function (Blueprint $table) {
                $table->unsignedInteger('matches_hosted')->default(0);
            });
        }
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
