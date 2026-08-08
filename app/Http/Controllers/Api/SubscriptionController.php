<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->subscriptions()->orderBy('next_billing_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'billing_cycle' => 'in:weekly,monthly,yearly',
            'next_billing_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['category_id'])) {
            abort_unless(
                $request->user()->categories()->whereKey($data['category_id'])->exists(),
                422,
                'Invalid category_id.'
            );
        }

        return $request->user()->subscriptions()->create($data);
    }

    public function show(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);
        return $subscription;
    }

    public function update(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric',
            'billing_cycle' => 'in:weekly,monthly,yearly',
            'next_billing_date' => 'sometimes|date',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['category_id'])) {
            abort_unless(
                $request->user()->categories()->whereKey($data['category_id'])->exists(),
                422,
                'Invalid category_id.'
            );
        }

        $subscription->update($data);
        return $subscription;
    }

    public function destroy(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);
        $subscription->delete();
        return response()->noContent();
    }
}
