<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\TimeTravelEvent;
use App\Models\LocationQueryResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

    /**
     * Query an agent's location at a specific historical or future time point
     * Time Complexity: O(log n) - Database index-based query
     *
     * @param User $user
     * @param string $timestamp
     * @return LocationQueryResult
     */
    public function queryLocationAtTime(User $user, string $timestamp): LocationQueryResult
    {
        $queryTime = \Carbon\CarbonImmutable::parse($timestamp);

        // Simple approach: find the most recent time travel event for this user
        // This assumes events are created in chronological order
        $event = TimeTravelEvent::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$event) {
            return new LocationQueryResult(
                $user->id,
                $user->name,
                $queryTime->toIso8601String(),
                null,          // "present" / default timeline
                'present',
                null,
                null,
                ['status' => 'No time travel events found']
            );
        }

        // For now, return the most recent event's location
        // This is a simplified approach - in a real system you'd want more sophisticated logic
        return new LocationQueryResult(
            $user->id,
            $user->name,
            $queryTime->toIso8601String(),
            $event->to_location,
            $event->event_type,
            $event->departure_timestamp->toDateTimeString(),
            optional($event->arrival_timestamp)->toDateTimeString(),
            $event->metadata ?? []
        );
    }

    /**
     * Query an agent's current/latest location
     * Time Complexity: O(1) using latest_travel_id
     *
     * @param User $user
     * @return LocationQueryResult
     */
    public function queryCurrentLocation(User $user): LocationQueryResult
    {
        $currentState = TimeTravelEvent::getCurrentState($user->id);

        if (!$currentState) {
            // User has no time travel history - they're at present time
            return new LocationQueryResult(
                $user->id,
                $user->name,
                now()->toIso8601String(),
                null, // Present time, no specific location
                'present',
                null,
                null,
                ['status' => 'No time travel history - at present time']
            );
        }

        // If user has returned to present time
        if ($currentState->event_type === 'return') {
            return new LocationQueryResult(
                $user->id,
                $user->name,
                now()->toIso8601String(),
                null, // Present time, no specific location
                'present',
                $currentState->arrival_timestamp->toDateTimeString(),
                null,
                ['status' => 'At present time']
            );
        }

        // User is currently time traveling
        return new LocationQueryResult(
            $user->id,
            $user->name,
            now()->toIso8601String(),
            $currentState->to_location,
            $currentState->event_type,
            $currentState->departure_timestamp->toDateTimeString(),
            $currentState->arrival_timestamp->toDateTimeString(),
            $currentState->metadata
        );
    }
}
