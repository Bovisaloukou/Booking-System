<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Provider $provider;

    private Service $service;

    private TimeSlot $timeSlot;

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

        $this->timeSlot = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);
    }

    // -------------------------------------------------------
    // Create Booking
    // -------------------------------------------------------

    public function test_authenticated_client_can_create_booking(): void
    {
        Event::fake([\App\Events\BookingCreated::class]);
        Notification::fake();

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
                'notes' => 'Coupe courte svp',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'reference',
                    'date',
                    'start_time',
                    'end_time',
                    'total_price',
                    'status',
                    'status_label',
                    'notes',
                    'provider',
                    'service',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price', '25.00')
            ->assertJsonPath('data.notes', 'Coupe courte svp');

        $this->assertDatabaseHas('bookings', [
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'status' => 'pending',
        ]);

        // Verify the time slot was marked as unavailable
        $this->assertDatabaseHas('time_slots', [
            'id' => $this->timeSlot->id,
            'is_available' => false,
        ]);
    }

    public function test_booking_generates_a_unique_reference(): void
    {
        Event::fake([\App\Events\BookingCreated::class]);
        Notification::fake();

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        $response->assertStatus(201);

        $reference = $response->json('data.reference');
        $this->assertNotNull($reference);
        $this->assertStringStartsWith('BK-', $reference);
    }

    public function test_unauthenticated_user_cannot_create_booking(): void
    {
        $response = $this->postJson('/api/bookings', [
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_create_booking_fails_with_missing_required_fields(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider_id', 'service_id', 'time_slot_id']);
    }

    public function test_create_booking_fails_with_invalid_provider_id(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => 99999,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider_id']);
    }

    public function test_create_booking_fails_with_invalid_service_id(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => 99999,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_create_booking_fails_with_invalid_time_slot_id(): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time_slot_id']);
    }

    public function test_cannot_book_unavailable_time_slot(): void
    {
        Event::fake([\App\Events\BookingCreated::class]);
        Notification::fake();

        // Mark the time slot as unavailable (already booked)
        $this->timeSlot->update(['is_available' => false]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        // The BookingService uses lockForUpdate + firstOrFail, so it should return 404
        $response->assertStatus(404);
    }

    public function test_time_slot_becomes_unavailable_after_booking(): void
    {
        Event::fake([\App\Events\BookingCreated::class]);
        Notification::fake();

        $this->assertTrue($this->timeSlot->is_available);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        $this->timeSlot->refresh();
        $this->assertFalse($this->timeSlot->is_available);
    }

    public function test_second_booking_for_same_slot_fails(): void
    {
        Event::fake([\App\Events\BookingCreated::class]);
        Notification::fake();

        // First booking succeeds
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ])->assertStatus(201);

        // Second booking for the same slot fails
        $otherClient = User::factory()->create();
        $otherClient->assignRole('client');

        $response = $this->actingAs($otherClient, 'sanctum')
            ->postJson('/api/bookings', [
                'provider_id' => $this->provider->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $this->timeSlot->id,
            ]);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // List My Bookings
    // -------------------------------------------------------

    public function test_authenticated_user_can_list_own_bookings(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'reference',
                        'date',
                        'start_time',
                        'end_time',
                        'total_price',
                        'status',
                        'status_label',
                        'provider',
                        'service',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_user_cannot_see_other_users_bookings(): void
    {
        Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('client');

        $response = $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_bookings_by_status(): void
    {
        Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $timeSlot2 = TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(4)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'is_available' => false,
        ]);

        Booking::create([
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

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/bookings?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_unauthenticated_user_cannot_list_bookings(): void
    {
        $response = $this->getJson('/api/bookings');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------
    // Show Booking
    // -------------------------------------------------------

    public function test_client_can_view_own_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/bookings/'.$booking->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'status' => 'pending',
                    'total_price' => '25.00',
                ],
            ]);
    }

    public function test_provider_can_view_their_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $providerUser = $this->provider->user;

        $response = $this->actingAs($providerUser, 'sanctum')
            ->getJson('/api/bookings/'.$booking->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $booking->id);
    }

    public function test_other_user_cannot_view_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('client');

        $response = $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/bookings/'.$booking->id);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/bookings/'.$booking->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $booking->id);
    }

    // -------------------------------------------------------
    // Cancel Booking
    // -------------------------------------------------------

    public function test_client_can_cancel_pending_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        // Mark slot as booked (as it would be after creating a booking)
        $this->timeSlot->update(['is_available' => false]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel', [
                'reason' => 'Changement de plans',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Changement de plans',
        ]);

        // Verify time slot is available again
        $this->assertDatabaseHas('time_slots', [
            'id' => $this->timeSlot->id,
            'is_available' => true,
        ]);
    }

    public function test_client_can_cancel_confirmed_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->timeSlot->update(['is_available' => false]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_completed_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(403);
    }

    public function test_cannot_cancel_already_cancelled_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_cancel_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('client');

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(403);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $this->timeSlot->update(['is_available' => false]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel', [
                'reason' => 'Annulation administrative',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_without_reason_is_allowed(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $this->timeSlot->update(['is_available' => false]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_unauthenticated_user_cannot_cancel_booking(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->timeSlot->id,
            'date' => $this->timeSlot->date,
            'start_time' => $this->timeSlot->start_time,
            'end_time' => $this->timeSlot->end_time,
            'total_price' => 25.00,
            'status' => BookingStatus::Pending,
        ]);

        $response = $this->postJson('/api/bookings/'.$booking->id.'/cancel');

        $response->assertStatus(401);
    }
}
