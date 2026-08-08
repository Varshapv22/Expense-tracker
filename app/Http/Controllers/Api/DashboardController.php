<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $period = $request->query('period', 'month');
        $anchor = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::now();

        [$start, $end] = $this->range($period, $anchor);
        [$prevStart, $prevEnd] = $this->range($period, $this->shiftBack($period, $anchor));

        $user = $request->user();

        $current = $this->totals($user, $start, $end);
        $previous = $this->totals($user, $prevStart, $prevEnd);

        $balance = (float) $user->accounts()->sum('opening_balance')
            + (float) $user->transactions()->where('type', 'income')->sum('amount')
            - (float) $user->transactions()->where('type', 'expense')->sum('amount');

        $response = [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'balance' => $balance,
            'income_total' => $current['income'],
            'expense_total' => $current['expense'],
            'net' => $current['income'] - $current['expense'],
            'savings_rate' => $current['income'] > 0
                ? round((($current['income'] - $current['expense']) / $current['income']) * 100, 1)
                : null,
            'previous' => [
                'start' => $prevStart->toDateString(),
                'end' => $prevEnd->toDateString(),
                'income_total' => $previous['income'],
                'expense_total' => $previous['expense'],
                'net' => $previous['income'] - $previous['expense'],
                'income_change_pct' => $this->pctChange($previous['income'], $current['income']),
                'expense_change_pct' => $this->pctChange($previous['expense'], $current['expense']),
            ],
            'category_breakdown' => $this->categoryBreakdown($user, $start, $end),
            'recent_transactions' => $this->recentTransactions($user),
            'cash_flow' => $this->cashFlow($user, $period, $start, $end),
            'upcoming_bills' => $this->upcomingBills($user),
            'pending_debts' => $this->pendingDebts($user),
            'subscriptions' => $this->subscriptionsSummary($user),
        ];

        if ($period === 'month') {
            $limit = $user->budget?->monthly_limit;
            $response['budget'] = [
                'monthly_limit' => $limit !== null ? (float) $limit : null,
                'remaining' => $limit !== null ? (float) $limit - $current['expense'] : null,
                'over_budget' => $limit !== null ? $current['expense'] > (float) $limit : null,
            ];
        } else {
            $response['budget'] = null;
        }

        return response()->json($response);
    }

    private function range(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => abort(422, 'period must be day, week, month, or year'),
        };
    }

    private function shiftBack(string $period, Carbon $anchor): Carbon
    {
        return match ($period) {
            'day' => $anchor->copy()->subDay(),
            'week' => $anchor->copy()->subWeek(),
            'month' => $anchor->copy()->subMonthNoOverflow(),
            'year' => $anchor->copy()->subYear(),
        };
    }

    private function totals($user, Carbon $start, Carbon $end): array
    {
        return [
            'income' => (float) $user->transactions()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount'),
            'expense' => (float) $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$start, $end])
                ->sum('amount'),
        ];
    }

    private function pctChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function categoryBreakdown($user, Carbon $start, Carbon $end): array
    {
        return $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get()
            ->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group, $name) => [
                'category' => $name,
                'total' => (float) $group->sum('amount'),
            ])
            ->values()
            ->sortByDesc('total')
            ->values()
            ->take(8)
            ->all();
    }

    private function recentTransactions($user): array
    {
        return $user->transactions()
            ->with(['account:id,name', 'category:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'title' => $t->title,
                'amount' => (float) $t->amount,
                'transaction_date' => $t->transaction_date->toDateString(),
                'account' => $t->account?->name,
                'category' => $t->category?->name,
            ])
            ->all();
    }

    private function cashFlow($user, string $period, Carbon $start, Carbon $end): array
    {
        $rows = $user->transactions()
            ->whereIn('type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$start, $end])
            ->get(['type', 'amount', 'transaction_date']);

        $bucketFormat = $period === 'year' ? 'Y-m' : 'Y-m-d';

        $buckets = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format($bucketFormat);
            $buckets[$key] = ['date' => $key, 'income' => 0.0, 'expense' => 0.0];
            $cursor = $period === 'year' ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        foreach ($rows as $row) {
            $key = $row->transaction_date->format($bucketFormat);
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][$row->type] += (float) $row->amount;
        }

        return array_values($buckets);
    }

    private function upcomingBills($user): array
    {
        return $user->bills()
            ->where('is_paid', false)
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'amount' => (float) $b->amount,
                'due_date' => $b->due_date->toDateString(),
                'overdue' => $b->due_date->isPast() && ! $b->due_date->isToday(),
            ])
            ->all();
    }

    private function pendingDebts($user): array
    {
        return $user->debts()
            ->where('remaining_amount', '>', 0)
            ->orderByRaw('due_date is null, due_date')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'direction' => $d->direction,
                'remaining_amount' => (float) $d->remaining_amount,
                'total_amount' => (float) $d->total_amount,
                'due_date' => $d->due_date?->toDateString(),
            ])
            ->all();
    }

    private function subscriptionsSummary($user): array
    {
        $active = $user->subscriptions()->where('is_active', true)->orderBy('next_billing_date')->get();

        return [
            'monthly_total' => round($active->sum(fn ($s) => $s->monthlyEquivalent()), 2),
            'active_count' => $active->count(),
            'items' => $active->take(5)->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'amount' => (float) $s->amount,
                'billing_cycle' => $s->billing_cycle,
                'next_billing_date' => $s->next_billing_date->toDateString(),
            ])->all(),
        ];
    }
}
