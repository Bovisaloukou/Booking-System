<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Provider $provider;

    private Service $service;

    private Booking $completedBooking;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'client', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'provider', 'guard_name' => 'web']);

        $this->client = User::factory()->create(['name' => 'Client Test']);
        $this->client->assignRole('client');

        $category = Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->service = Service::create([
            'category_id' => $category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $providerUser = User::factory()->create(['name' => 'Provider Test']);
        $providerUser->assignRole('provider');
        $this->provider = Provider::create([
            'user_id' => $providerUser->id,
            'bio' => 'Expert coiffeur',
            'speciality' => 'Coiffure',
            'hourly_rate' => 30.00,
            'is_active' => true,
        ]);
        $this->provider->services()->attach($this->service->id);

        $timeSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->subDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => false,
        ]);

        $this->completedBooking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot->id,
            'date' => $timeSlot->date,
            'start_time' => $timeSlot->start_time,
            'end_time' => $timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    // -------------------------------------------------------
    // Create Review for Completed Booking
    // -------------------------------------------------------

    public function test_client_can_review_completed_booking(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 5,
                'comment' => 'Excellent service, je recommande!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'rating',
                    'comment',
                    'client' => ['id', 'name'],
                    'created_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'rating' => 5,
                    'comment' => 'Excellent service, je recommande!',
                    'client' => [
                        'id' => $this->client->id,
                        'name' => 'Client Test',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->completedBooking->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 5,
            'comment' => 'Excellent service, je recommande!',
        ]);
    }

    public function test_review_without_comment_is_allowed(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 4,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', null);
    }

    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $response = $this->postJson('/api/reviews', [
            'booking_id' => $this->completedBooking->id,
            'rating' => 5,
            'comment' => 'Great!',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------
    // Cannot Review Non-Completed Booking
    // -------------------------------------------------------

    public function test_cannot_review_pending_booking(): void
    {
        $timeSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'is_available' => false,
        ]);

        $pendingBooking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot->id,
            'date' => $timeSlot->date,
            'start_time' => $timeSlot->start_time,
            'end_time' => $timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $pendingBooking->id,
                'rating' => 5,
                'comment' => 'Cannot review yet!',
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_review_confirmed_booking(): void
    {
        $timeSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'is_available' => false,
        ]);

        $confirmedBooking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot->id,
            'date' => $timeSlot->date,
            'start_time' => $timeSlot->start_time,
            'end_time' => $timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $confirmedBooking->id,
                'rating' => 4,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_review_cancelled_booking(): void
    {
        $timeSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '12:00',
            'end_time' => '12:30',
            'is_available' => true,
        ]);

        $cancelledBooking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot->id,
            'date' => $timeSlot->date,
            'start_time' => $timeSlot->start_time,
            'end_time' => $timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $cancelledBooking->id,
                'rating' => 1,
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------
    // Cannot Review Same Booking Twice
    // -------------------------------------------------------

    public function test_cannot_review_same_booking_twice(): void
    {
        // Create the first review
        Review::create([
            'booking_id' => $this->completedBooking->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 5,
            'comment' => 'First review',
        ]);

        // Try to create a second review for the same booking
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 3,
                'comment' => 'Second review attempt',
            ]);

        // Policy denies because $booking->review already exists
        $response->assertStatus(403);

        // Only one review should exist
        $this->assertDatabaseCount('reviews', 1);
    }

    // -------------------------------------------------------
    // Cannot Review Another User's Booking
    // -------------------------------------------------------

    public function test_cannot_review_another_users_booking(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('client');

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 5,
                'comment' => 'Not my booking!',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------
    // Validation Errors
    // -------------------------------------------------------

    public function test_review_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id', 'rating']);
    }

    public function test_review_fails_with_invalid_rating(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_fails_with_rating_above_5(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => $this->completedBooking->id,
                'rating' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_fails_with_nonexistent_booking_id(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/reviews', [
                'booking_id' => 99999,
                'rating' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id']);
    }

    // -------------------------------------------------------
    // List Provider Reviews (Public)
    // -------------------------------------------------------

    public function test_can_list_visible_reviews_for_provider(): void
    {
        Review::create([
            'booking_id' => $this->completedBooking->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 5,
            'comment' => 'Excellent!',
            'is_visible' => true,
        ]);

        // Create a second completed booking and hidden review
        $timeSlot2 = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->subDays(2)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '14:30',
            'is_available' => false,
        ]);

        $booking2 = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot2->id,
            'date' => $timeSlot2->date,
            'start_time' => $timeSlot2->start_time,
            'end_time' => $timeSlot2->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        Review::create([
            'booking_id' => $booking2->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 2,
            'comment' => 'Hidden review',
            'is_visible' => false,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'rating',
                        'comment',
                        'client' => ['id', 'name'],
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Excellent!');
    }

    public function test_provider_reviews_are_paginated(): void
    {
        // Create many reviews
        for ($i = 0; $i < 20; $i++) {
            $timeSlot = TimeSlot::create([
                'provider_id' => $this->provider->id,
                'date' => now()->subDays($i + 5)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '09:30',
                'is_available' => false,
            ]);

            $booking = Booking::create([
                'client_id' => $this->client->id,
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $timeSlot->id,
                'date' => $timeSlot->date,
                'start_time' => $timeSlot->start_time,
                'end_time' => $timeSlot->end_time,
                'total_price' => 25.00,
                'status' => BookingStatus::Completed,
                'completed_at' => now(),
            ]);

            Review::create([
                'booking_id' => $booking->id,
                'client_id' => $this->client->id,
                'provider_id' => $this->provider->id,
                'rating' => rand(3, 5),
                'comment' => "Review {$i}",
                'is_visible' => true,
            ]);
        }

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data') // default pagination is 15
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_provider_reviews_endpoint_returns_empty_when_no_reviews(): void
    {
        $response = $this->getJson('/api/providers/'.$this->provider->id.'/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_provider_reviews_are_ordered_by_latest_first(): void
    {
        $timeSlot2 = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->subDays(5)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '14:30',
            'is_available' => false,
        ]);

        $olderBooking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $timeSlot2->id,
            'date' => $timeSlot2->date,
            'start_time' => $timeSlot2->start_time,
            'end_time' => $timeSlot2->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Completed,
            'completed_at' => now()->subDays(5),
        ]);

        // Create the older review first
        $olderReview = Review::create([
            'booking_id' => $olderBooking->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 3,
            'comment' => 'Older review',
            'is_visible' => true,
        ]);

        // Backdate the older review via query builder (created_at is not fillable)
        Review::where('id', $olderReview->id)->update([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        // Create the newer review
        $newerReview = Review::create([
            'booking_id' => $this->completedBooking->id,
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'rating' => 5,
            'comment' => 'Newer review',
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // The newer review should come first (latest ordering)
        $response->assertJsonPath('data.0.comment', 'Newer review');
        $response->assertJsonPath('data.1.comment', 'Older review');
    }
}
