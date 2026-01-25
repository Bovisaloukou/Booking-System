<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // List Categories
    // -------------------------------------------------------

    public function test_can_list_active_categories(): void
    {
        Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'description' => 'Services de coiffure',
            'icon' => 'scissors',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::create([
            'name' => 'Massage',
            'slug' => 'massage',
            'description' => 'Services de massage',
            'icon' => 'hand',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Inactive category -- should NOT appear in results
        Category::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'icon'],
                ],
            ]);

        // Verify ordering by sort_order: Coiffure first, Massage second
        $response->assertJsonPath('data.0.slug', 'coiffure');
        $response->assertJsonPath('data.1.slug', 'massage');
    }

    public function test_categories_include_services_count(): void
    {
        $category = Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Service::create([
            'category_id' => $category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $category->id,
            'name' => 'Coupe Femme',
            'slug' => 'coupe-femme',
            'duration' => 60,
            'price' => 45.00,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.services_count', 2);
    }

    public function test_returns_empty_list_when_no_active_categories(): void
    {
        Category::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // Show Category by Slug
    // -------------------------------------------------------

    public function test_can_show_category_by_slug(): void
    {
        $category = Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'description' => 'Services de coiffure',
            'icon' => 'scissors',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/categories/coiffure');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => 'Coiffure',
                    'slug' => 'coiffure',
                    'description' => 'Services de coiffure',
                    'icon' => 'scissors',
                ],
            ]);
    }

    public function test_show_category_includes_active_services(): void
    {
        $category = Category::create([
            'name' => 'Coiffure',
            'slug' => 'coiffure',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Service::create([
            'category_id' => $category->id,
            'name' => 'Coupe Homme',
            'slug' => 'coupe-homme',
            'duration' => 30,
            'price' => 25.00,
            'is_active' => true,
        ]);

        Service::create([
            'category_id' => $category->id,
            'name' => 'Service Inactif',
            'slug' => 'service-inactif',
            'duration' => 30,
            'price' => 15.00,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/categories/coiffure');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.name', 'Coupe Homme');
    }

    public function test_show_category_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/categories/nonexistent');

        $response->assertStatus(404);
    }
}
