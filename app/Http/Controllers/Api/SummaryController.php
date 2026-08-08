<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SummaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $period = $request->query('period', 'month');
        $anchor = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::now();

        [$start, $end] = match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => abort(422, 'period must be day, week, month, or year'),
        };

        $expenseTotal = $request->user()->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        $incomeTotal = $request->user()->transactions()
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        $response = [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'total' => (float) $expenseTotal,
            'income_total' => (float) $incomeTotal,
            'net' => (float) $incomeTotal - (float) $expenseTotal,
        ];

        if ($period === 'month') {
            $limit = $request->user()->budget?->monthly_limit;
            $response['monthly_limit'] = $limit !== null ? (float) $limit : null;
            $response['remaining'] = $limit !== null ? (float) $limit - (float) $expenseTotal : null;
            $response['over_budget'] = $limit !== null ? $expenseTotal > (float) $limit : null;
        }

        return response()->json($response);
    }
}
