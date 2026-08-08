<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return Category::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->with('children')
            ->whereNull('parent_id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        return $request->user()->categories()->create($data);
    }

    public function show(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id || $category->user_id === null, 403);
        return $category->load('children');
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category->update($data);
        return $category;
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 403);
        $category->delete();
        return response()->noContent();
    }
}
