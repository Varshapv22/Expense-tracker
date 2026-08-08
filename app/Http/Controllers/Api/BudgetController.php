<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->budget;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'monthly_limit' => 'required|numeric|min:0',
        ]);

        return $request->user()->budget()->updateOrCreate([], $data);
    }
}
