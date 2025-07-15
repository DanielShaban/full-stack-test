<?php

namespace App\Http\Controllers;

use App\Repositories\TimeTravelRepository;
use Illuminate\Http\JsonResponse;

class TimeTravelController extends Controller
{
    public function __construct(protected TimeTravelRepository $repository)
    {
    }

    public function forward(): JsonResponse
    {
        $this->repository->forward();

        return response()->json(['message' => 'Time traveled forward by one week.']);
    }

    public function back(): JsonResponse
    {
        now()->subWeek();

        return response()->json(['message' => 'Time traveled back by one week.']);
    }
}
