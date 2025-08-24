<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TimeTravelEvent;
use App\Repositories\TimeTravelRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeTravelSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected TimeTravelRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->repository = new TimeTravelRepository();
    }

    /** @test */
    public function user_can_travel_to_historical_coordinates()
    {
        // Act: User travels to Ancient Rome coordinates
        $destination = '41.9028,12.4964'; // Ancient Rome coordinates
        $travelDate = Carbon::parse('0080-05-01 12:00:00');

        $result = $this->repository->travel($destination, $travelDate, $this->user);

        // Assert: User object returned
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);

        // Assert: Event created correctly
        $event = TimeTravelEvent::latest()->first();
        $this->assertEquals('travel', $event->event_type);
        $this->assertNull($event->from_location); // Server location (null)
        $this->assertEquals($destination, $event->to_location);
        $this->assertEquals($travelDate, $event->arrival_timestamp);
        $this->assertEquals($this->user->id, $event->user_id);

        // Assert: User's latest_travel_id updated
        $this->user->refresh();
        $this->assertEquals($event->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function user_cannot_travel_while_already_time_traveling()
    {
        // Arrange: User is already time traveling
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Act & Assert: Try to travel again should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot travel while already time traveling. Please return to present time first.');

        $this->repository->travel('48.8566,2.3522', Carbon::parse('1200-01-01'), $this->user);
    }

    /** @test */
    public function user_can_return_to_present_time()
    {
        // Arrange: User is time traveling
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Verify travel event was created
        $travelEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'travel')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $travelEvent);

        // Act: User returns to present
        $result = $this->repository->return($this->user);

        // Assert: User object returned
        $this->assertInstanceOf(User::class, $result);

        // Verify return event was created
        $returnEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'return')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $returnEvent);
        $this->assertEquals('41.9028,12.4964', $returnEvent->from_location);
        $this->assertNull($returnEvent->to_location); // Back to present
        $this->assertArrayHasKey('duration_away', $returnEvent->metadata);

        // Assert: User's latest_travel_id updated
        $this->user->refresh();
        $this->assertEquals($returnEvent->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function user_cannot_return_if_not_time_traveling()
    {
        // Act & Assert: Try to return when not traveling should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot return to present time while not time traveling.');

        $this->repository->return($this->user);
    }

    /** @test */
    public function user_can_move_forward_in_time()
    {
        // Arrange: User is time traveling
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Verify travel event was created
        $travelEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'travel')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $travelEvent);

        // Act: User moves forward in time
        $result = $this->repository->forward($this->user);

        // Assert: User object returned
        $this->assertInstanceOf(User::class, $result);

        // Verify forward event was created
        $forwardEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'forward')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $forwardEvent);
        $this->assertEquals('41.9028,12.4964', $forwardEvent->from_location);
        $this->assertEquals('41.9028,12.4964', $forwardEvent->to_location); // Same coordinates
        $this->assertArrayHasKey('time_difference', $forwardEvent->metadata);

        // Assert: User's latest_travel_id updated
        $this->user->refresh();
        $this->assertEquals($forwardEvent->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function user_cannot_move_forward_if_not_time_traveling()
    {
        // Act & Assert: Try to move forward when not traveling should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot move forward while not time traveling.');

        $this->repository->forward($this->user);
    }

    /** @test */
    public function user_can_move_backward_in_time()
    {
        // Arrange: User is time traveling
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Verify travel event was created
        $travelEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'travel')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $travelEvent);

        // Act: User moves backward in time
        $result = $this->repository->back($this->user);

        // Assert: User object returned
        $this->assertInstanceOf(User::class, $result);

        // Verify back event was created
        $backEvent = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'back')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $backEvent);
        $this->assertEquals('41.9028,12.4964', $backEvent->from_location);
        $this->assertEquals('41.9028,12.4964', $backEvent->to_location); // Same coordinates
        $this->assertArrayHasKey('time_difference', $backEvent->metadata);

        // Assert: User's latest_travel_id updated
        $this->user->refresh();
        $this->assertEquals($backEvent->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function user_cannot_move_backward_if_not_time_traveling()
    {
        // Act & Assert: Try to move backward when not traveling should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot move backward while not time traveling.');

        $this->repository->back($this->user);
    }

    /** @test */
    public function complete_time_travel_cycle_works_correctly()
    {
        // 1. Travel to historical coordinates
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        $this->user->refresh();
        $this->assertTrue($this->user->isCurrentlyTraveling());
        $this->assertEquals('41.9028,12.4964', $this->user->getCurrentLocationCoordinates());

        // 2. Move forward in time
        $this->repository->forward($this->user);

        $this->user->refresh();
        $this->assertTrue($this->user->isCurrentlyTraveling());
        $this->assertEquals('41.9028,12.4964', $this->user->getCurrentLocationCoordinates());

        // 3. Move backward in time
        $this->repository->back($this->user);

        $this->user->refresh();
        $this->assertTrue($this->user->isCurrentlyTraveling());
        $this->assertEquals('41.9028,12.4964', $this->user->getCurrentLocationCoordinates());

        // 4. Return to present
        $this->repository->return($this->user);

        $this->user->refresh();
        $this->assertFalse($this->user->isCurrentlyTraveling());
        $this->assertNull($this->user->getCurrentLocationCoordinates());

        // 5. Can travel again after returning
        $this->repository->travel('48.8566,2.3522', Carbon::parse('1200-01-01'), $this->user);

        $this->user->refresh();
        $this->assertTrue($this->user->isCurrentlyTraveling());
        $this->assertEquals('48.8566,2.3522', $this->user->getCurrentLocationCoordinates());
    }

    /** @test */
    public function get_current_state_returns_latest_event()
    {
        // Arrange: User has multiple time travel events
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);
        $this->repository->forward($this->user);
        $this->repository->back($this->user);

        // Act: Get current state
        $currentState = TimeTravelEvent::getCurrentState($this->user->id);

        // Assert: Returns the most recent event
        $this->assertInstanceOf(TimeTravelEvent::class, $currentState);
        $this->assertEquals('back', $currentState->event_type);
        $this->assertEquals('41.9028,12.4964', $currentState->to_location);
    }

    /** @test */
    public function get_current_state_returns_null_for_user_without_events()
    {
        // Act: Get current state for user with no time travel events
        $currentState = TimeTravelEvent::getCurrentState($this->user->id);

        // Assert: Returns null
        $this->assertNull($currentState);
    }

    /** @test */
    public function time_travel_events_are_properly_sequenced()
    {
        // Arrange: User performs a complete time travel cycle
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);
        $this->repository->forward($this->user);
        $this->repository->back($this->user);
        $this->repository->return($this->user);

        // Act: Get all events in order
        $events = TimeTravelEvent::where('user_id', $this->user->id)->orderBy('id')->get();

        // Assert: Correct sequence of events
        $this->assertCount(4, $events);

        // Check each event by ID (which should be in order)
        $this->assertEquals('travel', $events[0]->event_type);
        $this->assertEquals('forward', $events[1]->event_type);
        $this->assertEquals('back', $events[2]->event_type);
        $this->assertEquals('return', $events[3]->event_type);

        // Verify latest_travel_id points to the last event
        $this->user->refresh();
        $this->assertEquals($events[3]->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function metadata_is_properly_stored()
    {
        // Arrange: User travels and returns
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);
        $this->repository->return($this->user);

        // Assert: Travel event metadata
        $travelEvent = TimeTravelEvent::where('event_type', 'travel')->first();
        $this->assertNull($travelEvent->metadata); // No metadata for travel

        // Assert: Return event metadata
        $returnEvent = TimeTravelEvent::where('event_type', 'return')->first();
        $this->assertArrayHasKey('duration_away', $returnEvent->metadata);
        $this->assertStringContainsString('seconds', $returnEvent->metadata['duration_away']);
    }

    /** @test */
    public function server_location_is_stored_as_null()
    {
        // Act: User travels from server location
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Assert: Server location (present time) is stored as null
        $event = TimeTravelEvent::where('user_id', $this->user->id)->where('event_type', 'travel')->first();
        $this->assertInstanceOf(TimeTravelEvent::class, $event);
        $this->assertNull($event->from_location);
        $this->assertEquals('41.9028,12.4964', $event->to_location);
    }
}
