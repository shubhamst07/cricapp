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
            if (!Schema::hasColumn('match_posts', 'content_hi')) {
                $table->text('content_hi')->nullable();
            }
            if (!Schema::hasColumn('match_posts', 'content_hn')) {
                $table->text('content_hn')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_posts', function (Blueprint $table) {
            if (Schema::hasColumn('match_posts', 'content_hi')) {
                $table->dropColumn('content_hi');
            }
            if (Schema::hasColumn('match_posts', 'content_hn')) {
                $table->dropColumn('content_hn');
            }
        });
    }
};
