<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'admins' => User::where('role', 'admin')->count(),
                'new_last_30_days' => User::where('created_at', '>=', Carbon::now()->subDays(30))->count(),
            ],
            'transactions' => [
                'total' => Transaction::count(),
                'income_total' => (float) Transaction::where('type', 'income')->sum('amount'),
                'expense_total' => (float) Transaction::where('type', 'expense')->sum('amount'),
            ],
            'signup_trend' => $this->signupTrend(),
            'top_categories' => $this->topCategories(),
            'recent_users' => User::orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    private function signupTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $counts = User::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($u) => $u->created_at->format('Y-m'))
            ->map->count();

        $months = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $months[] = ['month' => $key, 'count' => $counts->get($key, 0)];
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    private function topCategories(): array
    {
        return Transaction::query()
            ->where('transactions.type', 'expense')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("coalesce(categories.name, 'Uncategorized') as category, sum(transactions.amount) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => (float) $row->total])
            ->all();
    }
}
