<?php

namespace App\Http\Controllers;

use App\Http\Requests\TravelRequest;
use App\Http\Requests\LocationQueryRequest;
use App\Http\Resources\TimeTravelResource;
use App\Http\Resources\LocationQueryResource;
use App\Models\User;
use App\Repositories\TimeTravelRepository;

class TimeTravelController extends Controller
{
    public function __construct(protected TimeTravelRepository $repository)
    {
    }

    public function travel(TravelRequest $request, User $user): TimeTravelResource
    {
        return new TimeTravelResource($this->repository->travel($request->location(), $request->travelTo(), $user));
    }

    public function return(User $user): TimeTravelResource
    {
        return new TimeTravelResource($this->repository->return($user));
    }

    public function forward(User $user): TimeTravelResource
    {
        return new TimeTravelResource($this->repository->forward($user));
    }

    public function back(User $user): TimeTravelResource
    {
        return new TimeTravelResource($this->repository->back($user));
    }

    public function queryLocation(LocationQueryRequest $request, User $user): LocationQueryResource
    {
        $timestamp = $request->query('at');

        if ($timestamp) {
            // Query location at specific time
            $location = $this->repository->queryLocationAtTime($user, $timestamp);
        } else {
            // Query current/latest location
            $location = $this->repository->queryCurrentLocation($user);
        }

        return new LocationQueryResource($location);
    }
}
