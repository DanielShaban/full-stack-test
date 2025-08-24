<?php

namespace App\Http\Controllers;

use App\Http\Requests\TravelRequest;
use App\Http\Resources\TimeTravelResource;
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
}
