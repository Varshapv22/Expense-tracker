<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->transactions()
            ->with(['account', 'toAccount', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('account_id')) {
            $accountId = $request->query('account_id');
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)->orWhere('to_account_id', $accountId);
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:income,expense,transfer',
            'account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required_if:type,transfer|nullable|exists:accounts,id|different:account_id',
            'category_id' => 'prohibited_if:type,transfer|nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        $this->authorizeOwnedResources($request, $data);

        return $request->user()->transactions()->create($data);
    }

    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        return $transaction->load(['account', 'toAccount', 'category']);
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'type' => 'sometimes|in:income,expense,transfer',
            'account_id' => 'sometimes|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id|different:account_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'title' => 'sometimes|string|max:255',
            'note' => 'nullable|string',
            'transaction_date' => 'sometimes|date',
        ]);

        $this->authorizeOwnedResources($request, $data);

        $transaction->update($data);
        return $transaction;
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        $transaction->delete();
        return response()->noContent();
    }

    /**
     * account_id / to_account_id / category_id must belong to the
     * authenticated user (or, for categories, be a global default) —
     * otherwise a user could link a transaction to someone else's
     * account by guessing an id.
     */
    private function authorizeOwnedResources(Request $request, array $data): void
    {
        $userId = $request->user()->id;

        foreach (['account_id', 'to_account_id', 'category_id'] as $field) {
            if (empty($data[$field])) {
                continue;
            }

            $model = $field === 'category_id' ? Category::class : Account::class;
            $owned = $model::where('id', $data[$field])
                ->where(function ($query) use ($userId, $model) {
                    $query->where('user_id', $userId);
                    if ($model === Category::class) {
                        $query->orWhereNull('user_id');
                    }
                })
                ->exists();

            abort_unless($owned, 403, "Invalid {$field}.");
        }
    }
}
