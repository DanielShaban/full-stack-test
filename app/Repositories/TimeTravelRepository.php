<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\TimeTravelEvent;
use Illuminate\Support\Carbon;

class TimeTravelRepository
{
    /**
     * Travel to a specific location and time
     * Time Complexity: O(1) for getCurrentState + O(1) for event creation + O(1) for user update
     * Overall: O(1)
     */
    public function travel(string $location, Carbon $date, User $user): User
    {
        // O(1) - Get current state using latest_travel_id (with fallback to O(log n))
        $currentState = TimeTravelEvent::getCurrentState($user->id);

        // Check if user is already time traveling
        if ($currentState && $currentState->to_location !== null) {
            throw new \InvalidArgumentException(
                'Cannot travel while already time traveling. Please return to present time first.'
            );
        }

        // O(1) - Create travel event
        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'travel',
            'from_location' => null, // Server location (present time) - stored as null
            'to_location' => $location,
            'departure_timestamp' => now(), // Departure from present time
            'arrival_timestamp' => $date,
            'metadata' => null // No metadata needed - all info is in main fields
        ]);

        $user->update(['latest_travel_id' => $event->id]);

        return $user;
    }

    /**
     * Return to present time
     * Time Complexity: O(1) for getCurrentState + O(1) for event creation + O(1) for user update
     * Overall: O(1)
     */
    public function return(User $user): User
    {
        $currentState = TimeTravelEvent::getCurrentState($user->id);

        if(!$currentState || $currentState->to_location === null) {
            throw new \InvalidArgumentException(
                'Cannot return to present time while not time traveling.'
            );
        }

        // O(1) - Create return event
        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'return',
            'from_location' => $currentState->to_location,
            'to_location' => null, // Return to present (no specific location)
            'departure_timestamp' => $currentState->arrival_timestamp,
            'arrival_timestamp' => now(),
            'metadata' => [
                'duration_away' => $currentState->arrival_timestamp->diffInSeconds(now()) . ' seconds'
            ]
        ]);

        // O(1) - Update user's latest travel ID for O(1) performance
        $user->update(['latest_travel_id' => $event->id]);

        return $user;
    }

    /**
     * Move forward in time (same location)
     * Time Complexity: O(1) for getCurrentState + O(1) for event creation + O(1) for user update
     * Overall: O(1)
     */
    public function forward(User $user): User
    {
        // O(1) - Get current state using latest_travel_id (with fallback to O(log n))
        $currentState = TimeTravelEvent::getCurrentState($user->id);

        if(!$currentState || $currentState->to_location === null) {
            throw new \InvalidArgumentException(
                'Cannot move forward while not time traveling.'
            );
        }

        $oldTimestamp = $currentState->arrival_timestamp;
        $newTimestamp = $oldTimestamp->addWeek();

        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'forward',
            'from_location' => $currentState->to_location,
            'to_location' => $currentState->to_location, // Same location, different time
            'departure_timestamp' => $oldTimestamp,
            'arrival_timestamp' => $newTimestamp,
            'metadata' => [
                // NOT REQUIRED, But it's a good idea to have it
                'time_difference' => $oldTimestamp->diffInSeconds($newTimestamp) . ' seconds'
            ]
        ]);

        // O(1) - Update user's latest travel ID for O(1) performance
        $user->update(['latest_travel_id' => $event->id]);

        return $user;
    }

    /**
     * Move backward in time (same location)
     * Time Complexity: O(1) for getCurrentState + O(1) for event creation + O(1) for user update
     * Overall: O(1)
     */
    public function back(User $user): User
    {
        $currentState = TimeTravelEvent::getCurrentState($user->id);

        if(!$currentState || $currentState->to_location === null) {
            throw new \InvalidArgumentException(
                'Cannot move backward while not time traveling.'
            );
        }

        $oldTimestamp = $currentState->arrival_timestamp;
        $newTimestamp = $oldTimestamp->subWeek();

        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'back',
            'from_location' => $currentState->to_location,
            'to_location' => $currentState->to_location, // Same location, different time
            'departure_timestamp' => $oldTimestamp,
            'arrival_timestamp' => $newTimestamp,
            'metadata' => [
                'time_difference' => $oldTimestamp->diffInSeconds($newTimestamp) . ' seconds'
            ]
        ]);

        // O(1) - Update user's latest travel ID for O(1) performance
        $user->update(['latest_travel_id' => $event->id]);

        return $user;
    }
}
