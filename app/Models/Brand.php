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
        'brief_document',
        'brief_document_name',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
