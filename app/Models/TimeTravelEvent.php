<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
     * Get the current state for a user
     * O(1) performance using latest_travel_id from users table
     * Falls back to O(log n) if latest_travel_id is not set
     */
    public static function getCurrentState(int $userId): ?self
    {
        // O(1) - Get user with latest_travel_id
        $user = \App\Models\User::select('latest_travel_id')->find($userId);

        if (!$user || !$user->latest_travel_id) {
            // Fallback: O(log n) - Query events table with sorting
            // This happens when latest_travel_id is not set (e.g., after migration)
            return static::where('user_id', $userId)
                ->orderBy('id', 'desc')
                ->limit(1)
                ->first();
        }

        // O(1) - Direct lookup by primary key using latest_travel_id
        return static::find($user->latest_travel_id);
    }
}
