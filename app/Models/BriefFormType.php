<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefFormType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'default_form_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function brandBriefForms(): HasMany
    {
        return $this->hasMany(BrandBriefForm::class);
    }
}
