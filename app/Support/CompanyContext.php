<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Str;

class CompanyContext
{
    public static function single(): bool
    {
        return (bool) config('crm.single_company');
    }

    public static function default(): ?Company
    {
        $name = (string) config('crm.default_company_name', 'Staffshaw');

        $company = Company::query()
            ->where(function ($query) use ($name) {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->orWhere('slug', Str::slug($name));
            })
            ->first();

        if ($company) {
            return $company;
        }

        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }

    /** Posted company on create. Single-company mode always uses the default. */
    public static function idForCreate(int|string|null $posted = null): ?int
    {
        if (! self::single()) {
            return $posted === null || $posted === '' ? null : (int) $posted;
        }

        return self::default()?->id;
    }

    /**
     * Single-company mode keeps the row's current company_id.
     * A blank value is filled with the default company. Nothing is reassigned.
     */
    public static function idForUpdate(int|string|null $existing, int|string|null $posted = null): ?int
    {
        if (! self::single()) {
            return $posted === null || $posted === '' ? null : (int) $posted;
        }

        if ($existing !== null && $existing !== '') {
            return (int) $existing;
        }

        return self::default()?->id;
    }
}
