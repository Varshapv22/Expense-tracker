<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->debts()->orderByRaw('due_date is null, due_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'direction' => 'in:owe,owed',
            'total_amount' => 'required|numeric',
            'remaining_amount' => 'required|numeric',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        return $request->user()->debts()->create($data);
    }

    public function show(Request $request, Debt $debt)
    {
        abort_unless($debt->user_id === $request->user()->id, 403);
        return $debt;
    }

    public function update(Request $request, Debt $debt)
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'direction' => 'in:owe,owed',
            'total_amount' => 'sometimes|numeric',
            'remaining_amount' => 'sometimes|numeric',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $debt->update($data);
        return $debt;
    }

    public function destroy(Request $request, Debt $debt)
    {
        abort_unless($debt->user_id === $request->user()->id, 403);
        $debt->delete();
        return response()->noContent();
    }
}
