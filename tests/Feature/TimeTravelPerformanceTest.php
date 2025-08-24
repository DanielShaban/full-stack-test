<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TimeTravelEvent;
use App\Repositories\TimeTravelRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeTravelPerformanceTest extends TestCase
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
    public function latest_travel_id_is_properly_maintained()
    {
        // Arrange: Create user with events
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Act: Verify latest_travel_id is correctly set
        $this->user->refresh();
        $latestEvent = TimeTravelEvent::where('user_id', $this->user->id)->orderBy('id', 'desc')->first();

        // Assert: latest_travel_id points to the most recent event
        $this->assertEquals($latestEvent->id, $this->user->latest_travel_id);

        // Create another event
        $this->repository->forward($this->user);

        // Assert: latest_travel_id is updated
        $this->user->refresh();
        $newLatestEvent = TimeTravelEvent::where('user_id', $this->user->id)->orderBy('id', 'desc')->first();
        $this->assertEquals($newLatestEvent->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function fallback_works_when_latest_travel_id_is_missing()
    {
        // Arrange: Create user with events but manually clear latest_travel_id
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);
        $this->repository->forward($this->user);

        // Manually clear latest_travel_id to test fallback
        $this->user->update(['latest_travel_id' => null]);

        // Act: Get current state using fallback
        $currentState = TimeTravelEvent::getCurrentState($this->user->id);

        // Assert: Fallback still works
        $this->assertInstanceOf(TimeTravelEvent::class, $currentState);
        $this->assertEquals('forward', $currentState->event_type);
        $this->assertEquals('41.9028,12.4964', $currentState->to_location);
    }

    /** @test */
    public function bulk_operations_create_correct_events()
    {
        // Test creating many events in sequence

        // Create 50 events rapidly
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        for ($i = 0; $i < 49; $i++) {
            $this->repository->forward($this->user);
        }

        // Assert: All events created
        $this->assertEquals(50, $this->user->timeTravelEvents()->count());

        // Assert: Latest event is correct
        $latestEvent = TimeTravelEvent::where('user_id', $this->user->id)->orderBy('id', 'desc')->first();
        $this->assertEquals('forward', $latestEvent->event_type);

        // Assert: latest_travel_id is set correctly
        $this->user->refresh();
        $this->assertEquals($latestEvent->id, $this->user->latest_travel_id);
    }

    /** @test */
    public function get_current_state_works_with_many_events()
    {
        // Arrange: Create user with many time travel events
        $this->repository->travel('41.9028,12.4964', Carbon::parse('0080-05-01'), $this->user);

        // Create 100 events
        for ($i = 0; $i < 100; $i++) {
            $this->repository->forward($this->user);
        }

        // Act: Get current state
        $currentState = TimeTravelEvent::getCurrentState($this->user->id);

        // Assert: Current state retrieved successfully
        $this->assertInstanceOf(TimeTravelEvent::class, $currentState);
        $this->assertEquals('forward', $currentState->event_type);
        $this->assertEquals('41.9028,12.4964', $currentState->to_location);

        // Assert: It's the 101st event (travel + 100 forward)
        $this->assertEquals(101, $currentState->id);
    }
}
