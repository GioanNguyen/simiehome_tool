<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Quote stats for today / this month / this year, scoped the same way
     * as the quote list itself: Sales only ever sees their own numbers,
     * Admin/Manager see the whole team. Admin/Manager additionally get a
     * per-Sales-person activity breakdown for the current month.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewAll = $user->canViewAllQuotes();

        $now = Carbon::now();
        $periods = [
            'today' => $now->copy()->startOfDay(),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
        ];

        $baseQuery = fn () => Quote::query()->when(! $canViewAll, fn ($query) => $query->where('user_id', $user->id));

        $stats = [];

        foreach ($periods as $key => $since) {
            $stats[$key] = [
                'count' => $baseQuery()->where('created_at', '>=', $since)->count(),
                'total' => (float) $baseQuery()->where('created_at', '>=', $since)->sum('grand_total'),
            ];
        }

        $salesActivity = collect();

        if ($canViewAll) {
            $monthStart = $periods['month'];
            $todayStart = $periods['today'];

            $salesActivity = User::query()
                ->where('role', User::ROLE_SALES)
                ->withCount([
                    'quotes as quotes_today_count' => fn ($query) => $query->where('created_at', '>=', $todayStart),
                    'quotes as quotes_month_count' => fn ($query) => $query->where('created_at', '>=', $monthStart),
                ])
                ->withSum([
                    'quotes as quotes_month_total' => fn ($query) => $query->where('created_at', '>=', $monthStart),
                ], 'grand_total')
                ->withMax('quotes as last_quote_at', 'created_at')
                ->orderByDesc('quotes_month_total')
                ->orderBy('name')
                ->get();
        }

        return view('dashboard', [
            'stats' => $stats,
            'canViewAll' => $canViewAll,
            'salesActivity' => $salesActivity,
        ]);
    }
}
