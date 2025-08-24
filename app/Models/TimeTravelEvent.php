<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class TimeTravelEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'from_location',
        'to_location',
        'departure_timestamp',
        'arrival_timestamp',
        'metadata',
    ];

    protected $casts = [
        'departure_timestamp' => 'datetime',
        'arrival_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Event types for time travel actions
     */
    const EVENT_TYPES = [
        'travel' => 'Direct travel to location and time',
        'return' => 'Return to present time',
        'forward' => 'Move forward in time',
        'back' => 'Move backward in time',
    ];

    /**
     * Get the user associated with this event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get events by type
     */
    public function scopeByType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get events within a time range
     */
    public function scopeInTimeRange($query, $startTime, $endTime)
    {
        return $query->whereBetween('arrival_timestamp', [$startTime, $endTime]);
    }

    /**
     * Scope to get events by location
     */
    public function scopeAtLocation($query, string $location)
    {
        return $query->where('to_location', $location);
    }

    /**
     * Scope to get latest event for a user
     */
    public function scopeLatestForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->limit(1);
    }

    /**
     * Get the current state for a user
     */
    public static function getCurrentState(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get current location for a user
     */
    public static function getCurrentLocation(int $userId): ?string
    {
        return static::getCurrentState($userId)?->to_location;
    }

    /**
     * Get current time for a user
     */
    public static function getCurrentTime(int $userId): ?string
    {
        return static::getCurrentState($userId)?->arrival_timestamp?->toDateTimeString();
    }

    /**
     * Check if a user is currently time traveling
     */
    public static function isUserTraveling(int $userId): bool
    {
        $currentState = static::getCurrentState($userId);
        return $currentState &&
               $currentState->to_location !== null &&
               $currentState->arrival_timestamp !== null;
    }

    /**
     * Get the agent's location at a specific point in time
     * Returns a single location (the most recent one)
     */
    public static function getAgentLocationAtTime(int $userId, $targetTime): ?string
    {
        $event = static::where('user_id', $userId)
            ->where('arrival_timestamp', '<=', $targetTime)
            ->orderBy('arrival_timestamp', 'desc')
            ->first();

        return $event?->to_location;
    }

    /**
     * Get ALL locations where the agent was present at a specific time
     * This handles cases where they visited the same location multiple times
     */
    public static function getAgentLocationsAtTime(int $userId, $targetTime): Collection
    {
        return static::where('user_id', $userId)
            ->where('arrival_timestamp', '<=', $targetTime)
            ->where('departure_timestamp', '>=', $targetTime)
            ->orderBy('arrival_timestamp', 'desc')
            ->get()
            ->pluck('to_location')
            ->unique()
            ->filter();
    }

    /**
     * Get the complete timeline of agent locations within a time range
     * Shows all periods when the agent was present
     */
    public static function getAgentTimelineRange(int $userId, $startTime, $endTime): Collection
    {
        return static::where('user_id', $userId)
            ->where(function($query) use ($startTime, $endTime) {
                $query->whereBetween('arrival_timestamp', [$startTime, $endTime])
                      ->orWhereBetween('departure_timestamp', [$startTime, $endTime])
                      ->orWhere(function($q) use ($startTime, $endTime) {
                          $q->where('departure_timestamp', '<=', $startTime)
                            ->where('arrival_timestamp', '>=', $endTime);
                      });
            })
            ->orderBy('departure_timestamp')
            ->get();
    }

    /**
     * Check if agent was at specific location during a time period
     */
    public static function wasAgentAtLocation(int $userId, string $location, $startTime, $endTime): bool
    {
        return static::where('user_id', $userId)
            ->where('to_location', $location)
            ->where('departure_timestamp', '<=', $endTime)
            ->where('arrival_timestamp', '>=', $startTime)
            ->exists();
    }

    /**
     * Get all time periods when agent was at specific location
     */
    public static function getAgentTimeAtLocation(int $userId, string $location): Collection
    {
        return static::where('user_id', $userId)
            ->where('to_location', $location)
            ->orderBy('departure_timestamp')
            ->get(['departure_timestamp', 'arrival_timestamp', 'metadata']);
    }
}
