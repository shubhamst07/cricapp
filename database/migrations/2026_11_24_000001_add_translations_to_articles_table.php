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
        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) {
                if (!Schema::hasColumn('articles', 'title_hi')) {
                    $table->string('title_hi', 255)->nullable()->after('title');
                }
                if (!Schema::hasColumn('articles', 'title_hinglish')) {
                    $table->string('title_hinglish', 255)->nullable()->after('title_hi');
                }
                if (!Schema::hasColumn('articles', 'category_hi')) {
                    $table->string('category_hi', 100)->nullable()->after('category');
                }
                if (!Schema::hasColumn('articles', 'category_hinglish')) {
                    $table->string('category_hinglish', 100)->nullable()->after('category_hi');
                }
                if (!Schema::hasColumn('articles', 'excerpt_hi')) {
                    $table->text('excerpt_hi')->nullable()->after('excerpt');
                }
                if (!Schema::hasColumn('articles', 'excerpt_hinglish')) {
                    $table->text('excerpt_hinglish')->nullable()->after('excerpt_hi');
                }
                if (!Schema::hasColumn('articles', 'content_hi')) {
                    $table->longText('content_hi')->nullable()->after('content');
                }
                if (!Schema::hasColumn('articles', 'content_hinglish')) {
                    $table->longText('content_hinglish')->nullable()->after('content_hi');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) {
                $columns = [
                    'title_hi', 'title_hinglish',
                    'category_hi', 'category_hinglish',
                    'excerpt_hi', 'excerpt_hinglish',
                    'content_hi', 'content_hinglish',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('articles', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
