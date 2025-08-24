<?php

namespace Tests\Unit;

use App\Models\LocationQueryResult;
use PHPUnit\Framework\TestCase;

class LocationQueryResultTest extends TestCase
{
    /** @test */
    public function it_creates_location_query_result_with_all_properties()
    {
        $result = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'travel',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z',
            ['status' => 'Traveling to Rome']
        );

        $this->assertEquals(1, $result->userId);
        $this->assertEquals('John Doe', $result->userName);
        $this->assertEquals('2025-01-01T12:00:00Z', $result->queryTimestamp);
        $this->assertEquals('41.9028,12.4964', $result->location);
        $this->assertEquals('travel', $result->eventType);
        $this->assertEquals('2025-01-01T10:00:00Z', $result->departureTime);
        $this->assertEquals('2025-01-01T12:00:00Z', $result->arrivalTime);
        $this->assertEquals(['status' => 'Traveling to Rome'], $result->metadata);
    }

    /** @test */
    public function it_creates_location_query_result_with_minimal_properties()
    {
        $result = new LocationQueryResult(
            2,
            'Jane Doe',
            '2025-01-01T12:00:00Z',
            null,
            'present',
            null,
            null
        );

        $this->assertEquals(2, $result->userId);
        $this->assertEquals('Jane Doe', $result->userName);
        $this->assertNull($result->location);
        $this->assertEquals('present', $result->eventType);
        $this->assertNull($result->departureTime);
        $this->assertNull($result->arrivalTime);
        $this->assertEquals([], $result->metadata); // Default empty array
    }

    /** @test */
    public function it_converts_to_array_correctly()
    {
        $result = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'travel',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z',
            ['status' => 'Traveling to Rome']
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertEquals([
            'user_id' => 1,
            'user_name' => 'John Doe',
            'query_timestamp' => '2025-01-01T12:00:00Z',
            'location' => '41.9028,12.4964',
            'event_type' => 'travel',
            'departure_time' => '2025-01-01T10:00:00Z',
            'arrival_time' => '2025-01-01T12:00:00Z',
            'metadata' => ['status' => 'Traveling to Rome'],
        ], $array);
    }

    /** @test */
    public function it_creates_from_array_correctly()
    {
        $data = [
            'user_id' => 3,
            'user_name' => 'Bob Smith',
            'query_timestamp' => '2025-01-01T12:00:00Z',
            'location' => '40.7128,-74.0060',
            'event_type' => 'travel',
            'departure_time' => '2025-01-01T10:00:00Z',
            'arrival_time' => '2025-01-01T12:00:00Z',
            'metadata' => ['status' => 'Traveling to New York']
        ];

        $result = LocationQueryResult::fromArray($data);

        $this->assertEquals(3, $result->userId);
        $this->assertEquals('Bob Smith', $result->userName);
        $this->assertEquals('40.7128,-74.0060', $result->location);
        $this->assertEquals('travel', $result->eventType);
        $this->assertEquals('2025-01-01T10:00:00Z', $result->departureTime);
        $this->assertEquals('2025-01-01T12:00:00Z', $result->arrivalTime);
        $this->assertEquals(['status' => 'Traveling to New York'], $result->metadata);
    }

    /** @test */
    public function it_handles_missing_metadata_in_from_array()
    {
        $data = [
            'user_id' => 4,
            'user_name' => 'Alice Johnson',
            'query_timestamp' => '2025-01-01T12:00:00Z',
            'location' => '51.5074,-0.1278',
            'event_type' => 'travel',
            'departure_time' => '2025-01-01T10:00:00Z',
            'arrival_time' => '2025-01-01T12:00:00Z'
            // metadata intentionally omitted
        ];

        $result = LocationQueryResult::fromArray($data);

        $this->assertEquals(4, $result->userId);
        $this->assertEquals('Alice Johnson', $result->userName);
        $this->assertEquals('51.5074,-0.1278', $result->location);
        $this->assertEquals([], $result->metadata); // Should default to empty array
    }

    /** @test */
    public function it_correctly_identifies_time_traveling_state()
    {
        $travelingResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'travel',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z'
        );

        $this->assertTrue($travelingResult->isTimeTraveling());
        $this->assertFalse($travelingResult->isAtPresentTime());
        $this->assertEquals('41.9028,12.4964', $travelingResult->getActiveLocation());
    }

    /** @test */
    public function it_correctly_identifies_present_time_state()
    {
        $presentResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            null,
            'present',
            null,
            null
        );

        $this->assertFalse($presentResult->isTimeTraveling());
        $this->assertTrue($presentResult->isAtPresentTime());
        $this->assertNull($presentResult->getActiveLocation());
    }

    /** @test */
    public function it_correctly_identifies_present_time_by_null_location()
    {
        $presentResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            null,
            'travel', // Even if event_type is travel, null location means present
            null,
            null
        );

        $this->assertFalse($presentResult->isTimeTraveling());
        $this->assertTrue($presentResult->isAtPresentTime());
        $this->assertNull($presentResult->getActiveLocation());
    }

    /** @test */
    public function it_handles_forward_event_type_correctly()
    {
        $forwardResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'forward',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z'
        );

        $this->assertTrue($forwardResult->isTimeTraveling());
        $this->assertFalse($forwardResult->isAtPresentTime());
        $this->assertEquals('41.9028,12.4964', $forwardResult->getActiveLocation());
    }

    /** @test */
    public function it_handles_back_event_type_correctly()
    {
        $backResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            '41.9028,12.4964',
            'back',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z'
        );

        $this->assertTrue($backResult->isTimeTraveling());
        $this->assertFalse($backResult->isAtPresentTime());
        $this->assertEquals('41.9028,12.4964', $backResult->getActiveLocation());
    }

    /** @test */
    public function it_handles_return_event_type_correctly()
    {
        $returnResult = new LocationQueryResult(
            1,
            'John Doe',
            '2025-01-01T12:00:00Z',
            null,
            'return',
            '2025-01-01T10:00:00Z',
            '2025-01-01T12:00:00Z'
        );

        $this->assertFalse($returnResult->isTimeTraveling());
        $this->assertTrue($returnResult->isAtPresentTime());
        $this->assertNull($returnResult->getActiveLocation());
    }
}
