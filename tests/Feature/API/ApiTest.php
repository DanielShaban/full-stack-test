<?php

use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

test('api travel to location and date', function () {

    $this->post(route('api.travel', ['user' => $this->user->id]), [
        'location' => 'Roman Colosseum',
        'travelTo' => '0080-05-01 12:00:00',
    ])->assertStatus(200)
    ->assertJsonStructure([
        'data' => [
            'user' => [
                'id',
                'name',
                'email',
            ],
            'location',
            'datetime',
            'traveledAt',
            'agentPerspectiveTimestamp',
        ],
    ]);
    $this->assertDatabaseHas(User::class, [
        'id' => $this->user->id,
        'location' => 'Roman Colosseum',
        'traveled_to_date' => '0080-05-01 12:00:00',
    ]);
});

test('api return to present time', function () {
    $this->post(route('api.travel', ['user' => $this->user->id]), [
        'location' => 'Roman Colosseum',
        'travelTo' => '0080-05-01 12:00:00',
    ]);

    $this->post(route('api.return', ['user' => $this->user->id]))
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'location',
                'datetime',
                'traveledAt',
                'agentPerspectiveTimestamp',
            ],
        ]);
    $this->assertDatabaseHas(User::class, [
        'id' => $this->user->id,
        'location' => null,
        'traveled_to_date' => now()->toDateTimeString(),
    ]);
});

test('api forward one week into the future', function () {
    $this->post(route('api.travel', ['user' => $this->user->id]), [
        'location' => 'Roman Colosseum',
        'travelTo' => '0080-05-01 12:00:00',
    ]);

    $this->get(route('travel.forward', ['user' => $this->user->id]))
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'location',
                'datetime',
                'traveledAt',
                'agentPerspectiveTimestamp',
            ],
        ]);
    $this->assertDatabaseHas(User::class, [
        'id' => $this->user->id,
        'traveled_to_date' => '0080-05-08 12:00:00',
    ]);
});

test('api reverse one week into the past', function () {
    $this->post(route('api.travel', ['user' => $this->user->id]), [
        'location' => 'Roman Colosseum',
        'travelTo' => '0080-05-01 12:00:00',
    ]);

    $this->put(route('travel.back', ['user' => $this->user->id]))
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'location',
                'datetime',
                'traveledAt',
                'agentPerspectiveTimestamp',
            ],
        ]);
    $this->assertDatabaseHas(User::class, [
        'id' => $this->user->id,
        'traveled_to_date' => '0080-04-24 12:00:00',
    ]);
});
