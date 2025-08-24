<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TimeTravelEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TimeTravelApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    /** @test */
    public function user_can_travel_via_api()
    {
        // Act: User travels via API
        $response = $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Event created in database
        $event = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'travel')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $event);
        $this->assertEquals('travel', $event->event_type);
        $this->assertEquals('41.9028,12.4964', $event->to_location);
        $this->assertNull($event->from_location); // Server location
    }

    /** @test */
    public function user_can_return_via_api()
    {
        // Arrange: User is time traveling
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: User returns via API
        $response = $this->putJson("/api/{$this->user->id}/return");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Return event created
        $returnEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'return')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $returnEvent);
        $this->assertEquals('return', $returnEvent->event_type);
        $this->assertEquals('41.9028,12.4964', $returnEvent->from_location);
        $this->assertNull($returnEvent->to_location); // Back to present
    }

    /** @test */
    public function user_can_move_forward_via_api()
    {
        // Arrange: User is time traveling
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: User moves forward via API
        $response = $this->patchJson("/api/{$this->user->id}/forward");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Forward event created
        $forwardEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'forward')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $forwardEvent);
        $this->assertEquals('forward', $forwardEvent->event_type);
        $this->assertEquals('41.9028,12.4964', $forwardEvent->from_location);
        $this->assertEquals('41.9028,12.4964', $forwardEvent->to_location); // Same coordinates
    }

    /** @test */
    public function user_can_move_backward_via_api()
    {
        // Arrange: User is time traveling
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: User moves backward via API
        $response = $this->patchJson("/api/{$this->user->id}/back");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Back event created
        $backEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'back')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $backEvent);
        $this->assertEquals('back', $backEvent->event_type);
        $this->assertEquals('41.9028,12.4964', $backEvent->from_location);
        $this->assertEquals('41.9028,12.4964', $backEvent->to_location); // Same coordinates
    }

    /** @test */
    public function api_returns_error_when_traveling_while_already_traveling()
    {
        // Arrange: User is already time traveling
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: Try to travel again
        $response = $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '48.8566,2.3522',
            'travelTo' => '1200-01-01 12:00:00'
        ]);

        // Assert: API returns error
        $response->assertStatus(500); // Or whatever status you want for this error
    }

    /** @test */
    public function api_returns_error_when_returning_without_traveling()
    {
        // Act: Try to return without traveling
        $response = $this->putJson("/api/{$this->user->id}/return");

        // Assert: API returns error
        $response->assertStatus(500); // Or whatever status you want for this error
    }

    /** @test */
    public function api_returns_error_when_moving_forward_without_traveling()
    {
        // Act: Try to move forward without traveling
        $response = $this->patchJson("/api/{$this->user->id}/forward");

        // Assert: API returns error
        $response->assertStatus(500); // Or whatever status you want for this error
    }

    /** @test */
    public function api_returns_error_when_moving_backward_without_traveling()
    {
        // Act: Try to move backward without traveling
        $response = $this->patchJson("/api/{$this->user->id}/back");

        // Assert: API returns error
        $response->assertStatus(500); // Or whatever status you want for this error
    }
}
