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
        Schema::create('user_travel_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('location'); // The coordinates where the user is traveling
            $table->timestamp('earliest_date_stayed'); // When they first arrived at this location
            $table->timestamp('latest_date_stayed'); // When they last stayed at this location (before returning or moving)
            $table->timestamp('session_started_at'); // When this session was created
            $table->timestamp('session_ended_at')->nullable(); // When this session ended (return to present)
            $table->boolean('is_active')->default(true); // Whether this session is currently active
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['earliest_date_stayed', 'latest_date_stayed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_travel_sessions');
    }
};
