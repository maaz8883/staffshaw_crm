<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\BrandBriefForm;
use Illuminate\Database\Eloquent\Collection;

class BriefFormSupport
{
    public static function briefFormUrl(BrandBriefForm $form, int $saleId): string
    {
        if (! $form->relationLoaded('brand')) {
            $form->load('brand');
        }

        $website = rtrim($form->brand->website, '/');
        $path    = '/' . ltrim($form->form_path, '/');

        return $website . $path . '?sale_id=' . $saleId;
    }

    /**
     * @return Collection<int, BrandBriefForm>
     */
    public static function activeFormsForBrand(Brand $brand): Collection
    {
        return $brand->briefForms()
            ->where('is_active', true)
            ->with(['brand', 'briefFormType'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    public static function resolveDocumentPath(BrandBriefForm $form): ?array
    {
        return $form->resolveDocumentPath();
    }
}
