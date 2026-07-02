<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website',
        'image',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function briefForms(): HasMany
    {
        return $this->hasMany(BrandBriefForm::class)->orderBy('sort_order')->orderBy('id');
    }
}
