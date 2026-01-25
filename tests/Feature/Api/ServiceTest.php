<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Category $category2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->category2 = Category::create([
            'name' => 'Massage',
            'slug' => 'massage',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    // -------------------------------------------------------
    // List Services
    // -------------------------------------------------------

    public function test_can_list_active_services(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Femme',
            'slug' => 'coupe-femme',
            'duration' => 60,
            'price' => 45.00,
            'is_active' => true,
        ]);

        // Inactive service -- should NOT appear
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Service Inactif',
            'slug' => 'service-inactif',
            'duration' => 30,
            'price' => 10.00,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'duration',
                        'formatted_duration',
                        'price',
                        'formatted_price',
                        'category',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_services_are_ordered_by_name(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Zebra Service',
            'slug' => 'zebra-service',
            'duration' => 30,
            'price' => 10.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Alpha Service',
            'slug' => 'alpha-service',
            'duration' => 30,
            'price' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.name', 'Alpha Service');
        $response->assertJsonPath('data.1.name', 'Zebra Service');
    }

    public function test_services_include_providers_count(): void
    {
        $service = Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $providerUser = User::factory()->create();
        $provider = Provider::create([
            'user_id' => $providerUser->id,
            'bio' => 'Expert coiffeur',
            'speciality' => 'Coiffure',
            'hourly_rate' => 30.00,
            'is_active' => true,
        ]);
        $provider->services()->attach($service->id);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.providers_count', 1);
    }

    // -------------------------------------------------------
    // Filter by Category
    // -------------------------------------------------------

    public function test_can_filter_services_by_category_slug(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $this->category2->id,
            'name' => 'Massage Relaxant',
            'slug' => 'massage-relaxant',
            'duration' => 60,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services?category=coiffure');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coupe Homme');
    }

    public function test_filter_by_nonexistent_category_returns_empty(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services?category=nonexistent');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // Search Services
    // -------------------------------------------------------

    public function test_can_search_services_by_name(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coloration',
            'slug' => 'coloration',
            'duration' => 90,
            'price' => 70.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services?search=Coupe');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coupe Homme');
    }

    public function test_search_is_case_insensitive_with_partial_match(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services?search=coupe');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coupe Homme');
    }

    // -------------------------------------------------------
    // Show Single Service
    // -------------------------------------------------------

    public function test_can_show_single_service_by_slug(): void
    {
        $service = Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'description' => 'Une coupe pour homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services/coupe-homme');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $service->id,
                    'name' => 'Coupe Homme',
                    'slug' => 'coupe-homme',
                    'description' => 'Une coupe pour homme',
                    'duration' => 30,
                    'price' => '25.00',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'duration',
                    'formatted_duration',
                    'price',
                    'formatted_price',
                    'image',
                    'category',
                    'providers_count',
                ],
            ]);
    }

    public function test_show_service_includes_category(): void
    {
        Service::create([
            'category_id' => $this->category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/services/coupe-homme');

        $response->assertStatus(200)
            ->assertJsonPath('data.category.slug', 'coiffure')
            ->assertJsonPath('data.category.name', 'Coiffure');
    }

    public function test_show_service_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/services/nonexistent');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // Pagination
    // -------------------------------------------------------

    public function test_services_are_paginated(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Service::create([
                'category_id' => $this->category->id,
                'name' => "Service {$i}",
                'slug' => "service-{$i}",
                'duration' => 30,
                'price' => 10.00,
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/services?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }
}
