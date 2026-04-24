<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPpcSpending;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Notifications\PpcSpendingAddedNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PpcController extends Controller
{
    public function index(Request $request): View
    {
        $user  = Auth::user();
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        // Show all teams with their spending
        $teams = Team::with(['company'])
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) use ($month, $year) {
                $team->month_spending = (float) TeamPpcSpending::where('team_id', $team->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->sum('amount');
                return $team;
            });

        $totalSpending = $teams->sum('month_spending');

        // All entries for selected month/year
        $spendings = TeamPpcSpending::where('month', $month)
            ->where('year', $year)
            ->with(['user', 'team'])
            ->latest()
            ->get();

        return view('admin.ppc.index', compact('teams', 'spendings', 'totalSpending', 'month', 'year'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'month'  => 'required|integer|min:1|max:12',
            'year'   => 'required|integer|min:2020|max:2100',
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:500',
        ]);

        if (! $user->team_id && ! $request->filled('team_id_override')) {
            return back()->withErrors(['team' => 'You are not assigned to any team.']);
        }

        $teamId = $request->filled('team_id_override')
            ? (int) $request->team_id_override
            : $user->team_id;

        $spending = TeamPpcSpending::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'month'   => $validated['month'],
            'year'    => $validated['year'],
            'amount'  => $validated['amount'],
            'notes'   => $validated['notes'] ?? null,
        ]);

        // Notify all admins
        $admins = User::whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))->get();
        Notification::send($admins, new PpcSpendingAddedNotification($spending));

        ActivityLogger::log($user, UserActivityLog::TYPE_PPC_ADDED,
            "Added PPC spending \${$spending->amount} for team #{$teamId}",
            ['spending_id' => $spending->id, 'amount' => $spending->amount, 'team_id' => $teamId]
        );

        return back()->with('success', 'PPC spending added successfully.');
    }

    public function destroy(TeamPpcSpending $spending): RedirectResponse
    {
        $user = Auth::user();

        // Only the creator or admin can delete
        if (! $user->hasRole('Admin') && $spending->user_id !== $user->id) {
            abort(403);
        }

        ActivityLogger::log($user, UserActivityLog::TYPE_PPC_DELETED,
            "Deleted PPC spending \${$spending->amount} (team #{$spending->team_id})",
            ['spending_id' => $spending->id, 'amount' => $spending->amount, 'team_id' => $spending->team_id]
        );

        $spending->delete();

        return back()->with('success', 'Entry deleted.');
    }
}
