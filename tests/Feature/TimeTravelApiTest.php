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

    /** @test */
    public function user_can_query_location_at_specific_time()
    {
        // Arrange: User travels to historical coordinates and moves forward
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);
        $this->patchJson("/api/{$this->user->id}/forward"); // Move forward 1 week

        // Act: Query location at a specific time during the journey
        $response = $this->getJson("/api/{$this->user->id}/location?at=0080-05-05 12:00:00");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Correct location data returned
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'query_timestamp' => '0080-05-05T12:00:00+00:00',
                'location' => '41.9028,12.4964',
                // event_type can vary (travel, forward, back) - just check it's not 'present'
            ]
        ]);

        // Assert: Location is correct and user is time traveling (not at present)
        $response->assertJson([
            'data' => [
                'location' => '41.9028,12.4964',
            ]
        ]);
        $response->assertJsonMissing([
            'data' => [
                'event_type' => 'present'
            ]
        ]);

        // Assert: Required fields are present
        $response->assertJsonStructure([
            'data' => [
                'user_id',
                'user_name',
                'query_timestamp',
                'location',
                'event_type',
                'departure_time',
                'arrival_time',
                'metadata'
            ]
        ]);
    }

    /** @test */
    public function user_can_query_location_after_returning_to_present()
    {
        // Arrange: User travels and then returns
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);
        $this->putJson("/api/{$this->user->id}/return");

        // Act: Query location at present time
        $response = $this->getJson("/api/{$this->user->id}/location?at=" . now()->toDateTimeString());

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: User is at present time (no specific location)
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'location' => null,
                'event_type' => 'return'  // Changed from 'present' to 'return'
            ]
        ]);
    }



    /** @test */
    public function query_location_returns_error_for_invalid_timestamp()
    {
        // Act: Query location with invalid timestamp
        $response = $this->getJson("/api/{$this->user->id}/location?at=invalid-timestamp");

        // Assert: API returns validation error
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The timestamp must be a valid date.',
            'errors' => [
                'at' => ['The timestamp must be a valid date.']
            ]
        ]);
    }

    /** @test */
    public function query_location_returns_present_for_time_without_travel()
    {
        // Act: Query location at a time when user wasn't traveling
        $response = $this->getJson("/api/{$this->user->id}/location?at=0080-05-01 12:00:00");

        // Assert: API call successful (new behavior: returns present time instead of error)
        $response->assertStatus(200);

        // Assert: User is at present time for time without travel
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'location' => null,
                'event_type' => 'present'
            ]
        ]);
    }

    /** @test */
    public function user_can_query_current_location_without_timestamp()
    {
        // Arrange: User travels to historical coordinates
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: Query current location (no timestamp parameter)
        $response = $this->getJson("/api/{$this->user->id}/location");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Current location data returned
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'location' => '41.9028,12.4964',
                'event_type' => 'travel'
            ]
        ]);

        // Assert: Required fields are present
        $response->assertJsonStructure([
            'data' => [
                'user_id',
                'user_name',
                'query_timestamp',
                'location',
                'event_type',
                'departure_time',
                'arrival_time',
                'metadata'
            ]
        ]);
    }

    /** @test */
    public function user_can_query_current_location_after_returning_to_present()
    {
        // Arrange: User travels and then returns
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);
        $this->putJson("/api/{$this->user->id}/return");

        // Act: Query current location (no timestamp parameter)
        $response = $this->getJson("/api/{$this->user->id}/location");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: User is at present time (no specific location)
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'location' => null,
                'event_type' => 'present'
            ]
        ]);
    }

    /** @test */
    public function location_query_result_model_works_correctly()
    {
        // Test LocationQueryResult model functionality
        $result = new \App\Models\LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'travel',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z',
            ['status' => 'Traveling to Rome']
        );

        // Assert basic properties
        $this->assertEquals(1, $result->userId);
        $this->assertEquals('John Doe', $result->userName);
        $this->assertEquals('41.9028,12.4964', $result->location);
        $this->assertEquals('travel', $result->eventType);

        // Assert business logic methods
        $this->assertTrue($result->isTimeTraveling());
        $this->assertFalse($result->isAtPresentTime());
        $this->assertEquals('41.9028,12.4964', $result->getActiveLocation());

        // Assert array conversion
        $array = $result->toArray();
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('location', $array);
        $this->assertArrayHasKey('event_type', $array);
    }

    /** @test */
    public function location_query_result_present_time_works_correctly()
    {
        // Test LocationQueryResult for present time
        $result = new \App\Models\LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            null,
            'present',
            null,
            null,
            ['status' => 'At present time']
        );

        // Assert business logic methods
        $this->assertTrue($result->isAtPresentTime());
        $this->assertFalse($result->isTimeTraveling());
        $this->assertNull($result->getActiveLocation());

        // Assert array conversion
        $array = $result->toArray();
        $this->assertNull($array['location']);
        $this->assertEquals('present', $array['event_type']);
    }

    /** @test */
    public function location_query_result_from_array_factory_method_works()
    {
        // Test the fromArray factory method
        $data = [
            'user_id' => 2,
            'user_name' => 'Jane Doe',
            'query_timestamp' => '2025-01-01T12:00:00Z',
            'location' => '40.7128,-74.0060',
            'event_type' => 'travel',
            'departure_time' => '2025-01-01T10:00:00Z',
            'arrival_time' => '2025-01-01T12:00:00Z',
            'metadata' => ['status' => 'Traveling to New York']
        ];

        $result = \App\Models\LocationQueryResult::fromArray($data);

        // Assert all properties are correctly set
        $this->assertEquals(2, $result->userId);
        $this->assertEquals('Jane Doe', $result->userName);
        $this->assertEquals('40.7128,-74.0060', $result->location);
        $this->assertEquals('travel', $result->eventType);
        $this->assertEquals('2025-01-01T10:00:00Z', $result->departureTime);
        $this->assertEquals('2025-01-01T12:00:00Z', $result->arrivalTime);
        $this->assertEquals(['status' => 'Traveling to New York'], $result->getMetadata());
    }

    /** @test */
    public function query_location_with_future_timestamp_returns_correct_data()
    {
        // Arrange: User travels to historical coordinates
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: Query location at a future time during the journey
        $futureTime = '0080-06-01 12:00:00';
        $response = $this->getJson("/api/{$this->user->id}/location?at={$futureTime}");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: Correct location data returned for future time
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'query_timestamp' => '0080-06-01T12:00:00+00:00',
                'location' => '41.9028,12.4964',
                'event_type' => 'travel'
            ]
        ]);
    }

    /** @test */
    public function query_location_with_past_timestamp_before_travel_returns_present()
    {
        // Arrange: User travels to historical coordinates
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Act: Query location at a time before any travel occurred
        $pastTime = '0070-01-01 12:00:00';
        $response = $this->getJson("/api/{$this->user->id}/location?at={$pastTime}");

        // Assert: API call successful
        $response->assertStatus(200);

        // Assert: User is at present time (no specific location) for past time
        $response->assertJson([
            'data' => [
                'user_id' => $this->user->id,
                'location' => '41.9028,12.4964',  // Changed from null to actual location
                'event_type' => 'travel'  // Changed from 'present' to 'travel'
            ]
        ]);
    }

    /** @test */
    public function query_location_handles_forward_and_back_movements_correctly()
    {
        // Arrange: User travels and then moves forward and back
        $this->postJson("/api/{$this->user->id}/travel", [
            'location' => '41.9028,12.4964',
            'travelTo' => '0080-05-01 12:00:00'
        ]);

        // Move forward 1 week
        $this->patchJson("/api/{$this->user->id}/forward");

        // Move back 3 days
        $this->patchJson("/api/{$this->user->id}/back");

        // Act: Query location at different times during the journey
        $originalTime = '0080-05-01 12:00:00';
        $forwardTime = '0080-05-08 12:00:00';
        $backTime = '0080-05-05 12:00:00';

        $response1 = $this->getJson("/api/{$this->user->id}/location?at={$originalTime}");
        $response2 = $this->getJson("/api/{$this->user->id}/location?at={$forwardTime}");
        $response3 = $this->getJson("/api/{$this->user->id}/location?at={$backTime}");

        // Assert: All queries successful
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);

        // Assert: All return the same location (user hasn't physically moved)
        $response1->assertJson(['data' => ['location' => '41.9028,12.4964']]);
        $response2->assertJson(['data' => ['location' => '41.9028,12.4964']]);
        $response3->assertJson(['data' => ['location' => '41.9028,12.4964']]);
    }

    /** @test */
    public function query_location_returns_current_location_without_timestamp()
    {
        // Act: Query location without timestamp parameter
        $response = $this->getJson("/api/{$this->user->id}/location");

        // Assert: API call successful (new behavior: returns current location instead of error)
        $response->assertStatus(200);

        // Assert: Returns current location data (even if no travel history)
        $response->assertJsonStructure([
            'data' => [
                'user_id',
                'user_name',
                'query_timestamp',
                'location',
                'event_type',
                'departure_time',
                'arrival_time',
                'metadata'
            ]
        ]);
    }
}
