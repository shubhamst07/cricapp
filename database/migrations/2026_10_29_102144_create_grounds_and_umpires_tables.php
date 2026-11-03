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
        // 1. Cricket Grounds Table
        Schema::create('grounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city');
            $table->string('address');
            $table->string('pitch_type')->default('Turf Pitch'); // Turf Pitch, Natural Grass, Cement / Matting, Box Cricket / Astro Turf
            $table->decimal('price_per_slot', 10, 2)->default(1000.00);
            $table->string('slot_duration')->default('2 Hours');
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->json('amenities')->nullable(); // Floodlights, Pavilion, Drinking Water, Parking, Net Practice, Boundary Rope, Changing Room
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('available'); // available, maintenance, booked
            $table->timestamps();
        });

        // 2. Ground Bookings Table
        Schema::create('ground_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ground_id')->constrained('grounds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('team_name');
            $table->date('booking_date');
            $table->string('time_slot'); // e.g. "06:00 AM - 09:00 AM", "04:00 PM - 07:00 PM"
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('contact_phone');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed
            $table->timestamps();
        });

        // 3. Umpire Profiles Table
        Schema::create('umpire_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city');
            $table->integer('experience_years')->default(1);
            $table->integer('matches_umpired')->default(0);
            $table->string('certification_level')->default('Club / Local League Umpire');
            $table->json('match_types')->nullable(); // Leather Ball, Tennis Ball, T20 Matches, Day/Night
            $table->decimal('fee_per_match', 10, 2)->default(500.00);
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_url')->nullable();
            $table->decimal('rating', 3, 1)->default(4.8);
            $table->string('status')->default('available'); // available, busy, inactive
            $table->timestamps();
        });

        // 4. Umpire Bookings Table
        Schema::create('umpire_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('umpire_profile_id')->constrained('umpire_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tournament_name')->nullable();
            $table->date('match_date');
            $table->string('match_time');
            $table->string('venue');
            $table->string('teams'); // e.g. "Royal Strikers vs Thunder Titans"
            $table->string('ball_type')->default('Tennis Ball');
            $table->decimal('total_fee', 10, 2)->default(0.00);
            $table->string('contact_phone');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('umpire_bookings');
        Schema::dropIfExists('umpire_profiles');
        Schema::dropIfExists('ground_bookings');
        Schema::dropIfExists('grounds');
    }
};
