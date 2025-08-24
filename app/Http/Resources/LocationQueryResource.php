<?php

namespace App\Http\Resources;

use App\Models\LocationQueryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationQueryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LocationQueryResult $result */
        $result = $this->resource;

        return $result->toArray();
    }
}
