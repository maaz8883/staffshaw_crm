<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const ADMIN   = 'Admin';
    public const MANAGER = 'Manager';
    public const AGENT   = 'Agent';
    public const PPC     = 'PPC';
    public const PROJECT_MANAGER = 'Project Manager';
    /** Production teams (Design, Development, SEO…): Trello + their own dashboard only, no sales data */
    public const PRODUCTION = 'Production';

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
