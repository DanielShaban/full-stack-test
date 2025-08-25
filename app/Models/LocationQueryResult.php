<?php

namespace App\Models;

use Carbon\Carbon;

class LocationQueryResult
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $queryTimestamp,
        public readonly ?array $locations
    ) {}

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            userName: $data['user_name'],
            queryTimestamp: $data['query_timestamp'],
            locations: $data['locations'] ?? null
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
            'locations' => $this->locations,
        ];
    }

    /**
     * Check if user is at present time
     */
    public function isAtPresentTime(): bool
    {
        return empty($this->locations);
    }

    /**
     * Check if user is currently time traveling
     */
    public function isTimeTraveling(): bool
    {
        return !empty($this->locations);
    }

    /**
     * Get the active location coordinates
     */
    public function getActiveLocation(): ?string
    {
        return $this->isTimeTraveling() && !empty($this->locations) ? $this->locations[0]['location'] : null;
    }

    /**
     * Get all locations
     */
    public function getAllLocations(): array
    {
        return $this->locations ?? [];
    }
}
