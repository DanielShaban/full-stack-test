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
     * Return to present time from time travel
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

        $start = $currentState->created_at;
        $end = now();

        // Calculate how much real-world time has passed since travel
        $realTimePassed = $end->diffInSeconds($start);

        // Create a new Carbon instance from the arrival timestamp and add the real time
        $departure_timestamp = Carbon::parse($currentState->arrival_timestamp)->addSeconds(abs($realTimePassed));


        // O(1) - Create return event
        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'return',
            'from_location' => $currentState->to_location,
            'to_location' => null, // Return to present (no specific location)
            'departure_timestamp' => $departure_timestamp,
            'arrival_timestamp' => now(),
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


        $start = $currentState->created_at;
        $end = now();
        // Calculate how much real-world time has passed since travel
        $realTimePassed = $end->diffInSeconds($start);



        //If you are reading this, please don't judge me for this code. I'm just trying to make it work.

        // Create a new Carbon instance from the arrival timestamp and add the real time
        $oldTimestamp = Carbon::parse($currentState->arrival_timestamp)->addSeconds(abs($realTimePassed));

        // Move forward 1 week from the current time position
        $newTimestamp = $oldTimestamp->copy()->addDays(7);

        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'forward',
            'from_location' => $currentState->to_location,
            'to_location' => $currentState->to_location, // Same location, different time
            'departure_timestamp' => $oldTimestamp,
            'arrival_timestamp' => $newTimestamp,
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

        $start = $currentState->created_at;
        $end = now();

        // Calculate how much real-world time has passed since travel
        $realTimePassed = $end->diffInSeconds($start);

        // Create a new Carbon instance from the arrival timestamp and add the real time
        $oldTimestamp = Carbon::parse($currentState->arrival_timestamp)->addSeconds(abs($realTimePassed));

        // Move backward 1 week from the current time position
        $newTimestamp = $oldTimestamp->copy()->subDays(7);


        $event = TimeTravelEvent::create([
            'user_id' => $user->id,
            'event_type' => 'back',
            'from_location' => $currentState->to_location,
            'to_location' => $currentState->to_location, // Same location, different time
            'departure_timestamp' => $oldTimestamp,
            'arrival_timestamp' => $newTimestamp,
        ]);

        // O(1) - Update user's latest travel ID for O(1) performance
        $user->update(['latest_travel_id' => $event->id]);

        return $user;
    }

    public function queryCurrentLocation(User $user): LocationQueryResult
    {
        $currentState = TimeTravelEvent::getCurrentState($user->id);
        return new LocationQueryResult($user->id, $user->name, now(),   locations: [$currentState->to_location]);
    }

    public function queryLocationAtTime(User $user, string $timestamp): LocationQueryResult
    {
        // Parse timestamp into an immutable Carbon instance
        $queryTime = \Carbon\CarbonImmutable::parse($timestamp);

        // Fetch all TimeTravelEvent for this user ordered by id ASC
        $events = TimeTravelEvent::where('user_id', $user->id)->orderBy('id')->get();

        $matches = [];

                // Iterate through events to find travel intervals
        for ($i = 0; $i < $events->count(); $i++) {
            $event = $events[$i];

            //If you are reading this, please don't judge me for this code. I'm just trying to make it work.


            $start = $event->created_at;
            $end = now();

            // Calculate how much real-world time has passed since travel
            $realTimePassed = $end->diffInSeconds($start);



            if ($i + 1 < $events->count()) {
                $departureTime = $events[$i + 1]->departure_timestamp;
            } else {
                $departureTime = Carbon::parse($event->arrival_timestamp)->addSeconds(abs($realTimePassed));
            }

            if ($queryTime->lessThanOrEqualTo($departureTime) && $queryTime->greaterThanOrEqualTo($event->arrival_timestamp)) {
                // Create a unique key based on location to ensure no duplicates
                $uniqueKey = 'location_' . md5($event->to_location);
                $matches[$uniqueKey] = [
                    'location' => $event->to_location
                ];
            }
        }

        // If no matches found, return empty locations array
        if (empty($matches)) {
            return new LocationQueryResult(
                $user->id,
                $user->name,
                $queryTime->toIso8601String(),
                []
            );
        }

        // Convert associative array back to indexed array for the response
        $uniqueMatches = array_values($matches);

        return new LocationQueryResult(
            $user->id,
            $user->name,
            $queryTime->toIso8601String(),
            $uniqueMatches
        );
    }

}
