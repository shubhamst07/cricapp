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
        // 1. Ensure users table has is_admin flag
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('email');
            });
        }

        // 2. Create articles / news / blog table
        if (!Schema::hasTable('articles')) {
            Schema::create('articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 255);
                $table->string('slug', 255)->unique();
                $table->string('category', 60)->default('General');
                $table->text('excerpt')->nullable();
                $table->longText('content');
                $table->string('featured_image', 500)->nullable();
                $table->string('author_name', 100)->nullable();
                $table->string('tags', 255)->nullable();
                $table->string('status', 20)->default('published');
                $table->unsignedInteger('views_count')->default(0);
                $table->unsignedInteger('reading_time')->default(3);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'published_at']);
                $table->index('category');
                $table->index('is_featured');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
    }
};
