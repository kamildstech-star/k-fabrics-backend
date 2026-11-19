<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of active categories with their nested children.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return CategoryResource::collection($categories);
    }
}
