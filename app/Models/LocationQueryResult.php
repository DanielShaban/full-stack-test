<?php

namespace App\Models;

use Carbon\Carbon;

class LocationQueryResult
{
    private array $metadata;

    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $queryTimestamp,
        public readonly ?string $location,
        public readonly string $eventType,
        public readonly ?string $departureTime,
        public readonly ?string $arrivalTime,
        ?array $metadata = null
    ) {
        $this->metadata = $metadata ?? [];
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            userName: $data['user_name'],
            queryTimestamp: $data['query_timestamp'],
            location: $data['location'],
            eventType: $data['event_type'],
            departureTime: $data['departure_time'],
            arrivalTime: $data['arrival_time'],
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Convert to array for API response
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'query_timestamp' => $this->queryTimestamp,
            'location' => $this->location,
            'event_type' => $this->eventType,
            'departure_time' => $this->departureTime,
            'arrival_time' => $this->arrivalTime,
            'metadata' => $this->getMetadata(),
        ];
    }

    /**
     * Check if user is at present time
     */
    public function isAtPresentTime(): bool
    {
        return $this->eventType === 'present' || $this->location === null;
    }

    /**
     * Check if user is currently time traveling
     */
    public function isTimeTraveling(): bool
    {
        return $this->eventType !== 'present' && $this->location !== null;
    }

    /**
     * Get the active location coordinates
     */
    public function getActiveLocation(): ?string
    {
        return $this->isTimeTraveling() ? $this->location : null;
    }
}
