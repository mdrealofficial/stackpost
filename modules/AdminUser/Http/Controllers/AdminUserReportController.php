<?php

namespace Modules\AdminUser\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\AdminRole;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class AdminUserReportController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['role:id,name,slug', 'plan:id,name,type'])
            ->withCount(['teams', 'ownedTeams'])
            ->get([
                'id',
                'name',
                'username',
                'email',
                'locale',
                'role_id',
                'plan_id',
                'is_super_admin',
                'created_at',
                'email_verified_at',
                'two_factor_confirmed_at',
                'plan_expires_at',
            ]);

        $teams = Team::query()
            ->with('owner')
            ->withCount('members')
            ->get();

        $now = now();
        $monthlySignups = collect(range(11, 0))->reverse()->map(function (int $offset) use ($users, $now) {
            $month = $now->copy()->subMonths($offset)->startOfMonth();
            $count = $users->filter(fn (User $user) => $user->created_at?->isSameMonth($month))->count();

            return [
                'label' => $month->format('M'),
                'value' => $count,
            ];
        });

        $recentDailySignups = collect(range(29, 0))->map(function (int $offset) use ($users, $now) {
            $date = $now->copy()->subDays($offset);
            $count = $users->filter(fn (User $user) => $user->created_at?->isSameDay($date))->count();

            return [
                'label' => $date->format('d M'),
                'value' => $count,
            ];
        });

        $recentWeeklySignups = collect(range(7, 0))->reverse()->map(function (int $offset) use ($users, $now) {
            $start = $now->copy()->startOfWeek()->subWeeks($offset);
            $end = $start->copy()->endOfWeek();
            $count = $users->filter(fn (User $user) => $user->created_at && $user->created_at->between($start, $end))->count();

            return [
                'label' => $start->format('d M'),
                'value' => $count,
            ];
        });

        $localeBreakdown = $users
            ->groupBy(fn (User $user) => $user->locale ?: 'unset')
            ->map(fn ($group, $locale) => [
                'label' => strtoupper((string) $locale),
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values();

        $roleBreakdown = $users
            ->groupBy(function (User $user) {
                if ($user->isSuperAdmin()) {
                    return __('Super admin');
                }

                return $user->role?->name ?: __('No role');
            })
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values();

        $planBreakdown = $users
            ->groupBy(fn (User $user) => $user->plan?->name ?: __('No plan'))
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values();

        $teamDistribution = $teams
            ->map(fn (Team $team) => [
                'label' => $team->name,
                'value' => $team->members_count,
            ])
            ->sortByDesc('value')
            ->take(6)
            ->values();

        $totalUsers = $users->count();
        $verifiedUsers = $users->whereNotNull('email_verified_at')->count();
        $twoFactorUsers = $users->whereNotNull('two_factor_confirmed_at')->count();
        $newUsersThisMonth = $users->filter(fn (User $user) => $user->created_at?->isSameMonth($now))->count();
        $newUsersLast30Days = $users->filter(fn (User $user) => $user->created_at && $user->created_at->gte($now->copy()->subDays(29)->startOfDay()))->count();
        $newUsersPrev30Days = $users->filter(fn (User $user) => $user->created_at && $user->created_at->between($now->copy()->subDays(59)->startOfDay(), $now->copy()->subDays(30)->endOfDay()))->count();
        $growthRate = $newUsersPrev30Days > 0
            ? (int) round((($newUsersLast30Days - $newUsersPrev30Days) / $newUsersPrev30Days) * 100)
            : ($newUsersLast30Days > 0 ? 100 : 0);

        $usersWithPlan = $users->whereNotNull('plan_id')->count();
        $expiringSoonUsers = $users->filter(fn (User $user) => $user->plan_expires_at && $user->plan_expires_at->between($now, $now->copy()->addDays(7)))->count();
        $expiredUsers = $users->filter(fn (User $user) => $user->plan_expires_at && $user->plan_expires_at->lt($now))->count();
        $adminUsers = $users->filter(fn (User $user) => $user->canAccessAdmin())->count();
        $superAdmins = $users->filter(fn (User $user) => $user->isSuperAdmin())->count();
        $usersWithoutRole = $users->filter(fn (User $user) => ! $user->isSuperAdmin() && ! $user->role_id)->count();
        $usersWithoutPlan = $users->filter(fn (User $user) => ! $user->plan_id)->count();
        $usersMissingVerification = $users->whereNull('email_verified_at')->count();
        $usersMissingTwoFactor = $users->whereNull('two_factor_confirmed_at')->count();

        $securitySegments = [
            [
                'name' => __('Verified + 2FA'),
                'y' => $users->filter(fn (User $user) => $user->email_verified_at && $user->two_factor_confirmed_at)->count(),
                'color' => '#10b981',
            ],
            [
                'name' => __('Verified only'),
                'y' => $users->filter(fn (User $user) => $user->email_verified_at && ! $user->two_factor_confirmed_at)->count(),
                'color' => '#0ea5e9',
            ],
            [
                'name' => __('2FA only'),
                'y' => $users->filter(fn (User $user) => ! $user->email_verified_at && $user->two_factor_confirmed_at)->count(),
                'color' => '#f59e0b',
            ],
            [
                'name' => __('Unverified'),
                'y' => $users->filter(fn (User $user) => ! $user->email_verified_at && ! $user->two_factor_confirmed_at)->count(),
                'color' => '#94a3b8',
            ],
        ];

        $attentionUsers = $users
            ->filter(function (User $user) use ($now) {
                return ! $user->email_verified_at
                    || ! $user->two_factor_confirmed_at
                    || ! $user->plan_id
                    || ($user->plan_expires_at && $user->plan_expires_at->between($now, $now->copy()->addDays(7)));
            })
            ->sortBy(function (User $user) use ($now) {
                return [
                    $user->email_verified_at ? 1 : 0,
                    $user->two_factor_confirmed_at ? 1 : 0,
                    $user->plan_id ? 1 : 0,
                    $user->plan_expires_at?->diffInSeconds($now, false) ?? PHP_INT_MAX,
                ];
            })
            ->take(8)
            ->values();

        return view('adminuser::report.index', [
            'metrics' => [
                'total_users' => $totalUsers,
                'verified_users' => $verifiedUsers,
                'verification_rate' => $totalUsers > 0 ? (int) round(($verifiedUsers / $totalUsers) * 100) : 0,
                'two_factor_users' => $twoFactorUsers,
                'two_factor_rate' => $totalUsers > 0 ? (int) round(($twoFactorUsers / $totalUsers) * 100) : 0,
                'new_users_this_month' => $newUsersThisMonth,
                'new_users_last_30_days' => $newUsersLast30Days,
                'new_users_previous_30_days' => $newUsersPrev30Days,
                'growth_rate' => $growthRate,
                'team_count' => $teams->count(),
                'avg_team_size' => $teams->count() > 0 ? round($teams->avg('members_count'), 1) : 0,
                'users_with_plan' => $usersWithPlan,
                'users_without_plan' => $usersWithoutPlan,
                'expiring_soon_users' => $expiringSoonUsers,
                'expired_users' => $expiredUsers,
                'admin_users' => $adminUsers,
                'super_admins' => $superAdmins,
                'users_without_role' => $usersWithoutRole,
                'users_missing_verification' => $usersMissingVerification,
                'users_missing_two_factor' => $usersMissingTwoFactor,
                'active_plan_rate' => $totalUsers > 0 ? (int) round(($usersWithPlan / $totalUsers) * 100) : 0,
            ],
            'monthlySignups' => $monthlySignups,
            'recentDailySignups' => $recentDailySignups,
            'recentWeeklySignups' => $recentWeeklySignups,
            'localeBreakdown' => $localeBreakdown,
            'roleBreakdown' => $roleBreakdown,
            'planBreakdown' => $planBreakdown,
            'teamDistribution' => $teamDistribution,
            'securitySegments' => $securitySegments,
            'largestTeams' => $teams->sortByDesc('members_count')->take(5)->values(),
            'latestUsers' => $users->sortByDesc('created_at')->take(8)->values(),
            'attentionUsers' => $attentionUsers,
            'roleCount' => AdminRole::query()->count(),
            'planCount' => AdminPlan::query()->count(),
        ]);
    }
}
