<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'speciality',
        'hourly_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot('custom_price', 'custom_duration')
            ->withTimestamps();
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function availableSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class)
            ->where('is_available', true)
            ->where('date', '>=', now()->toDateString());
    }

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->reviews()->where('is_visible', true)->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    public function getNameAttribute(): string
    {
        return $this->user->name;
    }
}
