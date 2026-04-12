<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamTarget;
use App\Models\User;
use App\Models\UserTarget;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dummy users, team, targets & sales for local/testing.
 * Login: agent1@test.com / agent2@test.com — password: password
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'StaffShaw')->first()
            ?? Company::query()->first();

        if ($company === null) {
            $this->command?->warn('DemoDataSeeder: no company found. Run CompanySeeder first.');

            return;
        }

        $agentRole = Role::query()->where('name', Role::AGENT)->first();
        if ($agentRole === null) {
            $this->command?->error('DemoDataSeeder: Agent role missing. Run RoleSeeder first.');

            return;
        }

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $team = Team::query()->updateOrCreate(
            ['name' => 'Demo Sales Team'],
            [
                'company_id' => $company->id,
                'description' => 'Seeded team for testing dashboard, targets & sales.',
            ]
        );

        $agent1 = User::query()->updateOrCreate(
            ['email' => 'agent1@test.com'],
            [
                'name' => 'Demo Agent One',
                'password' => Hash::make('password'),
                'role_id' => $agentRole->id,
                'team_id' => $team->id,
                'company_id' => $company->id,
            ]
        );

        $agent2 = User::query()->updateOrCreate(
            ['email' => 'agent2@test.com'],
            [
                'name' => 'Demo Agent Two',
                'password' => Hash::make('password'),
                'role_id' => $agentRole->id,
                'team_id' => $team->id,
                'company_id' => $company->id,
            ]
        );

        $team->update(['team_head_id' => $agent1->id]);

        $now = Carbon::now();
        $month = (int) $now->month;
        $year = (int) $now->year;

        TeamTarget::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'month' => $month,
                'year' => $year,
            ],
            [
                'target_amount' => 85000,
                'notes' => 'Demo team target (current month)',
            ]
        );

        foreach ([$agent1, $agent2] as $agent) {
            UserTarget::query()->updateOrCreate(
                [
                    'user_id' => $agent->id,
                    'team_id' => $team->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'target_amount' => 42000,
                    'notes' => 'Demo user target (current month)',
                ]
            );
        }

        // Previous month targets (reports / trends)
        $prev = $now->copy()->subMonth();
        TeamTarget::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'month' => $prev->month,
                'year' => $prev->year,
            ],
            ['target_amount' => 70000, 'notes' => 'Demo team target (previous month)']
        );

        Sale::query()
            ->where('title', 'like', '[Demo]%')
            ->delete();

        $d = fn (int $day) => Carbon::create($year, $month, min($day, $now->daysInMonth))->toDateString();
        $dPrev = fn (int $day) => $now->copy()->subMonth()->day(min($day, $now->copy()->subMonth()->daysInMonth))->toDateString();

        $rows = [
            // Approved + completed (count toward revenue)
            ['user' => 1, 'title' => '[Demo] Website Package — Acme', 'client' => 'Acme Ltd', 'amount' => 12500, 'date' => $d(3), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            ['user' => 1, 'title' => '[Demo] CRM Integration', 'client' => 'Beta Inc', 'amount' => 8900, 'date' => $d(5), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED, 'sale_type' => Sale::TYPE_UPSELL],
            ['user' => 1, 'title' => '[Demo] Support Retainer Q2', 'client' => 'Gamma Co', 'amount' => 5600, 'date' => $d(8), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            ['user' => 2, 'title' => '[Demo] Mobile App Phase 1', 'client' => 'Delta LLC', 'amount' => 15200, 'date' => $d(4), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            ['user' => 2, 'title' => '[Demo] API Development', 'client' => 'Epsilon', 'amount' => 7800, 'date' => $d(7), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            ['user' => 2, 'title' => '[Demo] UI Audit', 'client' => 'Zeta Studio', 'amount' => 3200, 'date' => $d(9), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            // Pending approval (for approval UI)
            ['user' => 2, 'title' => '[Demo] New Lead — Custom Portal', 'client' => 'Eta Corp', 'amount' => 11000, 'date' => $d(9), 'status' => 'pending', 'approval_status' => Sale::APPROVAL_PENDING, 'sale_type' => Sale::TYPE_UPSELL],
            // Approved but pending delivery status
            ['user' => 1, 'title' => '[Demo] Hosting Yearly', 'client' => 'Theta Web', 'amount' => 2400, 'date' => $d(6), 'status' => 'pending', 'approval_status' => Sale::APPROVAL_APPROVED],
            // Rejected example
            ['user' => 1, 'title' => '[Demo] Rejected Test Deal', 'client' => 'Fake Client', 'amount' => 999999, 'date' => $d(2), 'status' => 'cancelled', 'approval_status' => Sale::APPROVAL_REJECTED, 'approval_note' => 'Demo rejection'],
            ['user' => 1, 'title' => '[Demo] Client refund — hosting', 'client' => 'Theta Web', 'amount' => 450, 'date' => $d(4), 'status' => Sale::STATUS_REFUNDED, 'status_before_refund' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED, 'sale_type' => Sale::TYPE_FRONT, 'is_refunded' => true],
            // Previous month (charts / history)
            ['user' => 1, 'title' => '[Demo] Old Month Sale A', 'client' => 'Old A', 'amount' => 6000, 'date' => $dPrev(15), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
            ['user' => 2, 'title' => '[Demo] Old Month Sale B', 'client' => 'Old B', 'amount' => 4500, 'date' => $dPrev(20), 'status' => 'completed', 'approval_status' => Sale::APPROVAL_APPROVED],
        ];

        foreach ($rows as $row) {
            $user = $row['user'] === 1 ? $agent1 : $agent2;
            $status = $row['approval_status'];
            $decided = in_array($status, [Sale::APPROVAL_APPROVED, Sale::APPROVAL_REJECTED], true);
            $approvedAt = match ($status) {
                Sale::APPROVAL_APPROVED => Carbon::parse($row['date'])->setTime(14, 30),
                Sale::APPROVAL_REJECTED => Carbon::parse($row['date'])->setTime(16, 0),
                default => null,
            };

            $isRefunded = (bool) ($row['is_refunded'] ?? false);

            Sale::query()->create([
                'title' => $row['title'],
                'client_name' => $row['client'],
                'amount' => $row['amount'],
                'sale_date' => $row['date'],
                'user_id' => $user->id,
                'team_id' => $team->id,
                'company_id' => $company->id,
                'status' => $row['status'],
                'status_before_refund' => $row['status_before_refund'] ?? null,
                'sale_type' => ($row['sale_type'] ?? \App\Models\Sale::TYPE_FRONT),
                'is_refunded' => $isRefunded,
                'refunded_at' => $isRefunded ? Carbon::parse($row['date'])->setTime(10, 0) : null,
                'refunded_by' => $isRefunded ? $admin?->id : null,
                'approval_status' => $status,
                'approval_note' => $row['approval_note'] ?? null,
                'approved_by' => $decided ? $admin?->id : null,
                'approved_at' => $approvedAt,
                'notes' => 'Seeded demo sale',
            ]);
        }

        $this->command?->info('Demo data ready: agent1@test.com / agent2@test.com (password: password). Team "Demo Sales Team".');
    }
}
