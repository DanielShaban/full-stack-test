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
        Schema::table('users', function (Blueprint $table) {
            // Add latest_travel_id column
            $table->unsignedBigInteger('latest_travel_id')->nullable();

            // Add foreign key constraint
            $table->foreign('latest_travel_id')
                  ->references('id')
                  ->on('time_travel_events')
                  ->onDelete('set null'); // If event is deleted, set to null
        });

        // Populate existing data for users who have time travel events
        $this->populateLatestTravelIds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['latest_travel_id']);

            // Drop the column
            $table->dropColumn('latest_travel_id');
        });
    }

    /**
     * Populate latest_travel_id for existing users
     */
    private function populateLatestTravelIds(): void
    {
        // Get the latest event ID for each user who has time travel events
        $latestEvents = DB::table('time_travel_events')
            ->select('user_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('user_id')
            ->get();

        foreach ($latestEvents as $event) {
            DB::table('users')
                ->where('id', $event->user_id)
                ->update(['latest_travel_id' => $event->latest_id]);
        }
    }
};
