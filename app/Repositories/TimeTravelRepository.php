<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Carbon;

class TimeTravelRepository
{
    public function travel(string $location, Carbon $date, User $user): User
    {
        // user travels to the specified location and date
        $user->update(['location' => $location, 'traveled_to_date' => $date, 'traveled_at_date' => now()]);

        return $user;
    }

    public function return(User $user): User
    {
        // user returns to the present time
        $user->update(['location' => null, 'traveled_to_date' => now(), 'traveled_at_date' => now()]);

        return $user;
    }

    public function forward($user): User
    {
        // move one week into the agent's perspective future
        $user->update(['traveled_to_date' => $user->traveled_to_date->add($user->traveled_at_date->diffInSeconds(now()), 'seconds')->addWeek(), 'traveled_at_date' => now()]);

        return $user;
    }
}
