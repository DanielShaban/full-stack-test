<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create the new time_travel_events table
        Schema::create('time_travel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', ['travel', 'return', 'forward', 'back']);
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->datetime('departure_timestamp');
            $table->datetime('arrival_timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'arrival_timestamp']);
            $table->index(['user_id', 'departure_timestamp']);
            $table->index('event_type');
            $table->index('from_location');
            $table->index('to_location');
            $table->index(['user_id', 'created_at']);
        });

        // Step 2: Migrate existing time travel data from users table
        $this->migrateExistingData();

        // Step 3: Remove old columns from users table
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('users', 'traveled_to_date')) {
                $table->dropColumn('traveled_to_date');
            }
            if (Schema::hasColumn('users', 'traveled_at_date')) {
                $table->dropColumn('traveled_at_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add old columns back to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->datetime('traveled_to_date')->nullable();
            $table->datetime('traveled_at_date')->nullable();
        });

        // Migrate data back from events table
        $this->migrateDataBack();
    }

    /**
     * Migrate existing time travel data to the new events table
     */
    private function migrateExistingData(): void
    {
        // Get all users with time travel data
        $usersWithTimeTravel = DB::table('users')
            ->where(function($query) {
                $query->whereNotNull('location')
                      ->orWhereNotNull('traveled_to_date')
                      ->orWhereNotNull('traveled_at_date');
            })
            ->get();

        foreach ($usersWithTimeTravel as $user) {
            // Create event record for each user with time travel data
            DB::table('time_travel_events')->insert([
                'user_id' => $user->id,
                'event_type' => $this->determineEventType($user),
                'from_location' => $this->getFromLocation($user),
                'to_location' => $user->location,
                'departure_timestamp' => $user->traveled_at_date ?? now(),
                'arrival_timestamp' => $user->traveled_to_date ?? now(),
                'metadata' => json_encode([
                    'migrated_from' => 'users_table',
                    'original_location' => $user->location,
                    'original_traveled_to_date' => $user->traveled_to_date?->toDateTimeString(),
                    'original_traveled_at_date' => $user->traveled_at_date?->toDateTimeString(),
                ]),
                'created_at' => $user->traveled_at_date ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Determine event type based on existing data
     */
    private function determineEventType($user): string
    {
        if ($user->location && $user->traveled_to_date) {
            return 'travel';
        }
        return 'travel'; // Default to travel
    }

    /**
     * Get the departure location
     */
    private function getFromLocation($user): ?string
    {
        if ($user->location && $user->traveled_to_date) {
            return 'Present Time (2025)';
        }
        return null;
    }

    /**
     * Migrate data back from events table to users table
     */
    private function migrateDataBack(): void
    {
        // Get the latest event for each user
        $latestEvents = DB::table('time_travel_events')
            ->select('user_id', 'to_location', 'arrival_timestamp', 'created_at')
            ->whereIn('id', function($query) {
                $query->select(DB::raw('MAX(id)'))
                      ->from('time_travel_events')
                      ->groupBy('user_id');
            })
            ->get();

        foreach ($latestEvents as $event) {
            DB::table('users')
                ->where('id', $event->user_id)
                ->update([
                    'location' => $event->to_location,
                    'traveled_to_date' => $event->arrival_timestamp,
                    'traveled_at_date' => $event->created_at,
                ]);
        }
    }
};
