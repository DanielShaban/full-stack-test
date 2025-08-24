<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'latest_travel_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all time travel events for this user
     */
    public function timeTravelEvents(): HasMany
    {
        return $this->hasMany(TimeTravelEvent::class);
    }

    /**
     * Get the latest time travel event for this user
     */
    public function latestTimeTravelEvent()
    {
        return $this->belongsTo(TimeTravelEvent::class, 'latest_travel_id');
    }

    /**
     * Check if the user is currently time traveling
     */
    public function isCurrentlyTraveling(): bool
    {
        $currentState = TimeTravelEvent::getCurrentState($this->id);
        return $currentState &&
               $currentState->to_location !== null &&
               $currentState->arrival_timestamp !== null;
    }

    /**
     * Get the user's current location coordinates
     */
    public function getCurrentLocationCoordinates(): ?string
    {
        $currentState = TimeTravelEvent::getCurrentState($this->id);
        return $currentState?->to_location;
    }
}
