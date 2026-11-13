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
        Schema::table('match_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('match_posts', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->index();
            }
            if (!Schema::hasColumn('match_posts', 'over_metric')) {
                $table->string('over_metric')->nullable()->index();
            }
            if (!Schema::hasColumn('match_posts', 'ball_metric')) {
                $table->decimal('ball_metric', 6, 2)->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_posts', function (Blueprint $table) {
            if (Schema::hasColumn('match_posts', 'is_approved')) {
                $table->dropColumn('is_approved');
            }
            if (Schema::hasColumn('match_posts', 'over_metric')) {
                $table->dropColumn('over_metric');
            }
            if (Schema::hasColumn('match_posts', 'ball_metric')) {
                $table->dropColumn('ball_metric');
            }
        });
    }
};
