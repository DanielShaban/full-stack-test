<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeTravelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        // Get current time travel state
        $currentState = $user->latestTimeTravelEvent;
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'currentState' => $currentState ? [
                'event_type' => $currentState->event_type,
                'from_location' => $currentState->from_location,
                'to_location' => $currentState->to_location,
                'departure_timestamp' => $currentState->departure_timestamp?->toDateTimeString(),
                'arrival_timestamp' => $currentState->arrival_timestamp?->toDateTimeString(),
                'metadata' => $currentState->metadata,
            ] : null,
            'isCurrentlyTraveling' => $user->isCurrentlyTraveling(),
            'currentLocation' => $user->getCurrentLocationCoordinates(),
        ];
    }
}
