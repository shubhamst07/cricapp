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
        // 1. Permanent Location / City based Cricket Circles (e.g. "Shimla Weekend Cricketers")
        Schema::create('cricket_circles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->index();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('whatsapp_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Circle Members
        Schema::create('circle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cricket_circle_id')->constrained('cricket_circles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role')->default('member'); // 'admin', 'member'
            $table->timestamps();

            $table->unique(['cricket_circle_id', 'user_id']);
        });

        // 2. Match Meetups / Player Requests (e.g. "Sunday 7:30 AM Match - Need 4 Players in Shimla")
        Schema::create('match_meetups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Organizer
            $table->foreignId('ground_id')->nullable()->constrained('grounds')->nullOnDelete(); // Linked Ground
            $table->foreignId('cricket_circle_id')->nullable()->constrained('cricket_circles')->nullOnDelete(); // Linked Circle
            $table->string('title');
            $table->string('city')->index();
            $table->string('location_name');
            $table->string('location_address')->nullable();
            $table->date('match_date')->index();
            $table->string('match_time')->default('07:00 AM');
            $table->string('ball_type')->default('tennis'); // tennis, leather, tape, box_turf
            $table->string('match_format')->default('friendly'); // friendly, tournament_practice, box_cricket, open_net
            $table->string('skill_level')->default('all'); // all, beginner, intermediate, advanced
            $table->unsignedInteger('total_players')->default(11);
            $table->unsignedInteger('slots_needed')->default(4);
            $table->string('cost_per_player')->nullable(); // Free, ₹100, etc.
            $table->string('contact_phone')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('whatsapp_group_link')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, full, completed, cancelled
            $table->timestamps();
        });

        // Meetup Participants (Players who joined)
        Schema::create('meetup_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_meetup_id')->constrained('match_meetups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('player_name');
            $table->string('player_role')->default('All-Rounder'); // Batsman, Bowler, All-Rounder, Wicket-Keeper
            $table->string('contact_phone')->nullable();
            $table->string('status')->default('confirmed'); // confirmed, interested, cancelled
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['match_meetup_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetup_participants');
        Schema::dropIfExists('match_meetups');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('cricket_circles');
    }
};
