<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::query()
            ->whereNull('user_id')
            ->whereNull('parent_id')
            ->with('children')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create($data + ['user_id' => null]);

        AuditLog::record($request->user(), 'category.created', $category, "Created global category {$category->name}");

        return $category;
    }

    public function show(Category $category)
    {
        abort_unless($category->user_id === null, 404);

        return $category->load('children');
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->user_id === null, 404);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category->update($data);

        AuditLog::record($request->user(), 'category.updated', $category, "Updated global category {$category->name}", $data);

        return $category;
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($category->user_id === null, 404);

        AuditLog::record($request->user(), 'category.deleted', null, "Deleted global category {$category->name}", ['category_id' => $category->id]);

        $category->delete();

        return response()->noContent();
    }
}
