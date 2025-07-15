<?php

namespace App\Repositories;

class TimeTravelRepository
{
    public function forward(): void
    {
        now()->addWeek();
    }
}
