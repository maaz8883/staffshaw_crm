<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BrandBriefForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'brief_form_type_id',
        'name',
        'form_path',
        'document',
        'document_name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'brand_id'           => 'integer',
        'brief_form_type_id' => 'integer',
        'sort_order'         => 'integer',
        'is_active'          => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function briefFormType(): BelongsTo
    {
        return $this->belongsTo(BriefFormType::class);
    }

    public function urlForSale(Sale $sale): string
    {
        $website = rtrim($this->brand->website, '/');
        $path    = '/' . ltrim($this->form_path, '/');

        return $website . $path . '?sale_id=' . $sale->id;
    }

    public function hasDocument(): bool
    {
        return $this->resolveDocumentPath() !== null;
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    public function resolveDocumentPath(): ?array
    {
        if ($this->document) {
            $path = storage_path('app/public/' . $this->document);

            if (is_file($path)) {
                return [
                    'path'     => $path,
                    'filename' => $this->document_name ?? basename($this->document),
                ];
            }
        }

        if (! $this->relationLoaded('brand')) {
            $this->load('brand');
        }

        $brandSlug = Str::slug($this->brand->name);
        $typeSlug  = $this->briefFormType?->slug ?? Str::slug($this->name);

        foreach (['pdf', 'docx', 'doc'] as $ext) {
            $path = public_path("brief-form/{$brandSlug}-{$typeSlug}.{$ext}");

            if (is_file($path)) {
                return [
                    'path'     => $path,
                    'filename' => "{$brandSlug}-{$typeSlug}.{$ext}",
                ];
            }
        }

        $legacySlug = Str::slug($this->brand->name);

        foreach (['pdf', 'docx', 'doc'] as $ext) {
            $path = public_path("brief-form/{$legacySlug}.{$ext}");

            if (is_file($path) && $typeSlug === 'website') {
                return [
                    'path'     => $path,
                    'filename' => "{$legacySlug}.{$ext}",
                ];
            }
        }

        return null;
    }
}
