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

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'location' => $user->location,
            'datetime' => $user->traveled_to_date->toDateTimeString(),
            'traveledAt' => $user->traveled_at_date->toDateTimeString(),
            'agentPerspectiveTimestamp' => $user->traveled_to_date->add($user->traveled_at_date->diffInSeconds(now()), 'seconds'),
        ];
    }
}
