<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Categories
 *
 * Endpoints for browsing service categories.
 */
class CategoryController extends Controller
{
    /**
     * List Categories
     *
     * Retrieve all active categories, ordered by sort order, with a count of their services.
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Coiffure",
     *       "slug": "coiffure",
     *       "description": "Services de coiffure professionnels",
     *       "is_active": true,
     *       "sort_order": 1,
     *       "services_count": 5
     *     },
     *     {
     *       "id": 2,
     *       "name": "Massage",
     *       "slug": "massage",
     *       "description": "Massages et soins du corps",
     *       "is_active": true,
     *       "sort_order": 2,
     *       "services_count": 3
     *     }
     *   ]
     * }
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::where('is_active', true)
            ->withCount('services')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Show Category
     *
     * Retrieve a single category with its active services.
     *
     * @unauthenticated
     *
     * @urlParam category int required The ID of the category. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Coiffure",
     *     "slug": "coiffure",
     *     "description": "Services de coiffure professionnels",
     *     "is_active": true,
     *     "sort_order": 1,
     *     "services": [
     *       {
     *         "id": 1,
     *         "name": "Coupe homme",
     *         "slug": "coupe-homme",
     *         "price": 25.00,
     *         "duration": 30,
     *         "is_active": true
     *       }
     *     ]
     *   }
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Category]."
     * }
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource(
            $category->load(['services' => fn ($q) => $q->where('is_active', true)])
        );
    }
}
