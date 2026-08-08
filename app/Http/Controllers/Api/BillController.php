<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->bills()->orderBy('due_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'recurrence' => 'in:none,weekly,monthly,yearly',
            'category_id' => 'nullable|exists:categories,id',
            'is_paid' => 'boolean',
        ]);

        if (! empty($data['category_id'])) {
            abort_unless(
                $request->user()->categories()->whereKey($data['category_id'])->exists(),
                422,
                'Invalid category_id.'
            );
        }

        return $request->user()->bills()->create($data);
    }

    public function show(Request $request, Bill $bill)
    {
        abort_unless($bill->user_id === $request->user()->id, 403);
        return $bill;
    }

    public function update(Request $request, Bill $bill)
    {
        abort_unless($bill->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric',
            'due_date' => 'sometimes|date',
            'recurrence' => 'in:none,weekly,monthly,yearly',
            'category_id' => 'nullable|exists:categories,id',
            'is_paid' => 'boolean',
        ]);

        if (! empty($data['category_id'])) {
            abort_unless(
                $request->user()->categories()->whereKey($data['category_id'])->exists(),
                422,
                'Invalid category_id.'
            );
        }

        $bill->update($data);
        return $bill;
    }

    public function destroy(Request $request, Bill $bill)
    {
        abort_unless($bill->user_id === $request->user()->id, 403);
        $bill->delete();
        return response()->noContent();
    }
}
