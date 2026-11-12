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
        Schema::create('match_overs', function (Blueprint $table) {
            $table->id();
            $table->string('match_id', 64)->index();
            $table->string('over_key', 32); // '105', '104', 'notes', etc.
            $table->integer('over_number')->nullable()->index();
            $table->unsignedTinyInteger('inning')->default(1);
            $table->integer('runs')->default(0);
            $table->integer('wickets')->default(0);
            $table->string('bowler')->nullable();
            $table->string('batting_team')->nullable();
            $table->string('summary')->nullable();
            $table->longText('deliveries'); // JSON encoded deliveries & notes
            $table->timestamps();

            $table->unique(['match_id', 'over_key'], 'match_over_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_overs');
    }
};
