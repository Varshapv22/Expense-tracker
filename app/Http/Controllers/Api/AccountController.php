<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->accounts;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bank,credit_card,wallet,cash',
            'bank_name' => 'nullable|string|max:255',
            'account_number_last4' => 'nullable|digits:4',
            'opening_balance' => 'numeric',
            'currency' => 'string|size:3',
        ]);

        return $request->user()->accounts()->create($data);
    }

    public function show(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);
        return $account;
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:bank,credit_card,wallet,cash',
            'bank_name' => 'nullable|string|max:255',
            'account_number_last4' => 'nullable|digits:4',
            'opening_balance' => 'numeric',
            'currency' => 'string|size:3',
        ]);

        $account->update($data);
        return $account;
    }

    public function destroy(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);
        $account->delete();
        return response()->noContent();
    }
}
