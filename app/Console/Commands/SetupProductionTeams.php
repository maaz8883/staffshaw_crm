<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-time setup of the production structure:
 *   Production (main team, Trello: trello@) → sub-teams SMM, SEO, Development, Writers, Ebook, Design
 *   (each on its own Trello account), and the production users with the "Production" role.
 *
 * Safe to run again: existing teams are reused and existing users are never changed.
 * New users get a random password, shown once in the terminal.
 */
class SetupProductionTeams extends Command
{
    protected $signature = 'crm:setup-production
        {--company=StaffShaw : Company the teams and users belong to}
        {--password= : Password for these users (also resets it for the listed users that already exist)}';

    protected $description = 'Create the Production team, its department sub-teams and production users';

    /** Production head (head of the main "Production" team). */
    private const HEAD = ['email' => 'fahad@staffshaw.com', 'name' => 'Fahad'];

    /** Department sub-team => [Trello account to connect, members]. */
    private const DEPARTMENTS = [
        'SMM' => ['smm@staffshaw.com', []],
        'SEO' => ['seo@staffshaw.com', [['asif@staffshaw.com', 'Asif']]],
        'Development' => ['development@staffshaw.com', [
            ['saad@staffshaw.com', 'Saad'],
            ['sameer@staffshaw.com', 'Sameer'],
            ['arsalan@staffshaw.com', 'Arsalan'],
        ]],
        'Writers' => ['writers@staffshaw.com', []],
        'Ebook' => ['ebook@staffshaw.com', []],
        'Design' => ['design@staffshaw.com', [['adi@staffshaw.com', 'Adi']]],
    ];

    public function handle(): int
    {
        $company = Company::query()->whereRaw('LOWER(name) = ?', [Str::lower($this->option('company'))])->first();
        if (! $company) {
            $this->error('Company "'.$this->option('company').'" not found. Use --company="<name>".');

            return self::FAILURE;
        }
        $roleId = Role::query()->where('name', Role::PRODUCTION)->value('id');
        if (! $roleId) {
            $this->error('Role "Production" missing — run: php artisan migrate --force');

            return self::FAILURE;
        }

        $created = [];
        $skipped = [];
        $trello = [];

        DB::transaction(function () use ($company, $roleId, &$created, &$skipped, &$trello) {
            $production = Team::query()->firstOrCreate(
                ['name' => 'Production', 'parent_team_id' => null],
                ['company_id' => $company->id]
            );
            if (! $production->is_production) {
                $production->update(['is_production' => true]); // sub-teams follow (Team::saved)
            }
            $trello[] = ['Production (main)', 'trello@staffshaw.com'];

            $head = $this->user(self::HEAD['email'], self::HEAD['name'], $production, $company, $roleId, $created, $skipped);
            if ($head && ! $production->team_head_id) {
                $production->syncLeaders([$head->id]);
            }

            foreach (self::DEPARTMENTS as $name => [$account, $members]) {
                $dept = Team::query()->firstOrCreate(
                    ['name' => $name, 'parent_team_id' => $production->id],
                    ['company_id' => $company->id]
                );
                $trello[] = ['Production › '.$name, $account];
                foreach ($members as [$email, $memberName]) {
                    $this->user($email, $memberName, $dept, $company, $roleId, $created, $skipped);
                }
            }
        });

        if ($created) {
            $this->info('Users created — share each password privately; it is not stored anywhere readable:');
            $this->table(['Name', 'Email', 'Team', 'Password'], $created);
        }
        if ($skipped) {
            $this->warn($this->option('password')
                ? 'Already existed — only the password was reset (fix role / team in Users if needed):'
                : 'Already existed — left unchanged (fix role / team in Users if needed):');
            $this->table(['Email', 'Current team'], $skipped);
        }
        $this->info('Next: Trello → pick each team → Connect Trello with that account\'s API key + token:');
        $this->table(['CRM team', 'Trello account'], $trello);
        $this->line('Until a department is connected its members see "Trello not connected" (never trello@\'s boards).');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, string>>  $created
     * @param  array<int, array<int, string>>  $skipped
     */
    private function user(string $email, string $name, Team $team, Company $company, int $roleId, array &$created, array &$skipped): ?User
    {
        $email = Str::lower($email);
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $fixed = (string) $this->option('password');
        if ($existing) {
            if ($fixed !== '') {
                $existing->update(['password' => Hash::make($fixed)]);
            }
            $skipped[] = [$email, $existing->team?->name ?? '—'];

            return $existing;
        }

        $password = $fixed !== '' ? $fixed : Str::password(14, symbols: false);
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $roleId,
            'team_id' => $team->id,
            'company_id' => $company->id,
            'account_status' => User::ACCOUNT_ACTIVE,
        ]);
        $created[] = [$name, $email, ($team->parent_team_id ? 'Production › ' : '').$team->name, $password];

        return $user;
    }
}
