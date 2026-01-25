<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

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

        $providerUser = User::factory()->create(['name' => 'Pierre Martin']);
        $this->provider = Provider::create([
            'user_id' => $providerUser->id,
            'bio' => 'Expert coiffeur depuis 10 ans',
            'speciality' => 'Coiffure',
            'hourly_rate' => 30.00,
            'is_active' => true,
        ]);
        $this->provider->services()->attach($this->service->id);
    }

    // -------------------------------------------------------
    // List Providers
    // -------------------------------------------------------

    public function test_can_list_active_providers(): void
    {
        // Create an inactive provider -- should NOT appear
        $inactiveUser = User::factory()->create();
        Provider::create([
            'user_id' => $inactiveUser->id,
            'bio' => 'Inactive provider',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/providers');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'bio',
                        'speciality',
                        'hourly_rate',
                        'services',
                        'available_slots_count',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_providers_include_user_and_services_data(): void
    {
        $response = $this->getJson('/api/providers');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Pierre Martin')
            ->assertJsonPath('data.0.bio', 'Expert coiffeur depuis 10 ans')
            ->assertJsonPath('data.0.speciality', 'Coiffure')
            ->assertJsonPath('data.0.services.0.name', 'Coupe Homme');
    }

    public function test_providers_include_available_slots_count(): void
    {
        // Create some available time slots in the future
        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'is_available' => true,
        ]);

        // Booked slot -- should NOT be counted
        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '11:30',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/providers');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.available_slots_count', 2);
    }

    // -------------------------------------------------------
    // Filter by Service ID
    // -------------------------------------------------------

    public function test_can_filter_providers_by_service_id(): void
    {
        $category2 = Category::create([
            'name' => 'Massage',
            'slug' => 'massage',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $otherService = Service::create([
            'category_id' => $category2->id,
            'name' => 'Massage Relaxant',
            'slug' => 'massage-relaxant',
            'duration' => 60,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create(['name' => 'Marie Dupont']);
        $otherProvider = Provider::create([
            'user_id' => $otherUser->id,
            'bio' => 'Masseuse professionnelle',
            'speciality' => 'Massage',
            'hourly_rate' => 40.00,
            'is_active' => true,
        ]);
        $otherProvider->services()->attach($otherService->id);

        // Filter by the haircut service -- should return only Pierre
        $response = $this->getJson('/api/providers?service_id='.$this->service->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pierre Martin');

        // Filter by the massage service -- should return only Marie
        $response = $this->getJson('/api/providers?service_id='.$otherService->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Marie Dupont');
    }

    public function test_can_search_providers_by_name(): void
    {
        $otherUser = User::factory()->create(['name' => 'Marie Dupont']);
        Provider::create([
            'user_id' => $otherUser->id,
            'bio' => 'Masseuse',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/providers?search=Pierre');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pierre Martin');
    }

    // -------------------------------------------------------
    // Show Provider
    // -------------------------------------------------------

    public function test_can_show_single_provider(): void
    {
        $response = $this->getJson('/api/providers/'.$this->provider->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->provider->id,
                    'name' => 'Pierre Martin',
                    'bio' => 'Expert coiffeur depuis 10 ans',
                    'speciality' => 'Coiffure',
                    'hourly_rate' => '30.00',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'bio',
                    'speciality',
                    'hourly_rate',
                    'average_rating',
                    'services',
                    'available_slots_count',
                ],
            ]);
    }

    public function test_show_provider_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/providers/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // Available Slots
    // -------------------------------------------------------

    public function test_can_get_available_slots_for_provider(): void
    {
        $futureDate = now()->addDays(2)->toDateString();

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $futureDate,
            'start_time' => '10:00',
            'end_time' => '10:30',
            'is_available' => true,
        ]);

        // Booked slot -- should NOT appear
        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $futureDate,
            'start_time' => '11:00',
            'end_time' => '11:30',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/slots');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'date', 'start_time', 'end_time', 'is_available'],
                ],
            ]);
    }

    public function test_available_slots_excludes_past_dates(): void
    {
        // Past date slot
        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->subDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        // Future date slot
        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/slots');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_available_slots_by_date(): void
    {
        $date1 = now()->addDays(1)->toDateString();
        $date2 = now()->addDays(2)->toDateString();

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $date1,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $date2,
            'start_time' => '10:00',
            'end_time' => '10:30',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/slots?date='.$date1);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', $date1);
    }

    public function test_slots_are_ordered_by_date_and_start_time(): void
    {
        $date = now()->addDays(1)->toDateString();

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $date,
            'start_time' => '14:00',
            'end_time' => '14:30',
            'is_available' => true,
        ]);

        TimeSlot::create([
            'provider_id' => $this->provider->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/providers/'.$this->provider->id.'/slots');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.start_time', '09:00')
            ->assertJsonPath('data.1.start_time', '14:00');
    }

    // -------------------------------------------------------
    // Pagination
    // -------------------------------------------------------

    public function test_providers_are_paginated(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->create();
            Provider::create([
                'user_id' => $user->id,
                'bio' => "Provider {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/providers?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }
}
