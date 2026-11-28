<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_connections')) {
            return;
        }

        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('provider', 32)->index(); // 'instagram' | 'youtube'
            $table->string('account_id', 128)->nullable();
            $table->string('account_name')->nullable();
            $table->string('page_id', 128)->nullable();
            $table->string('page_name')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_connections');
    }
};
