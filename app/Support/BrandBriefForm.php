<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Support\Str;

class BrandBriefForm
{
    /** @var list<string> */
    private const DOCUMENT_EXTENSIONS = ['pdf', 'docx', 'doc'];

    public static function briefFormUrl(Brand $brand, int $saleId): string
    {
        return rtrim($brand->website, '/') . '/brief-form?sale_id=' . $saleId;
    }

    public static function documentSlug(Brand $brand): string
    {
        return Str::slug($brand->name);
    }

    public static function hasDocument(Brand $brand): bool
    {
        return self::resolveDocumentPath($brand) !== null;
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    public static function resolveDocumentPath(Brand $brand): ?array
    {
        if ($brand->brief_document) {
            $path = storage_path('app/public/' . $brand->brief_document);

            if (is_file($path)) {
                return [
                    'path'     => $path,
                    'filename' => $brand->brief_document_name ?? basename($brand->brief_document),
                ];
            }
        }

        $slug = self::documentSlug($brand);

        foreach (self::DOCUMENT_EXTENSIONS as $ext) {
            $path = public_path("brief-form/{$slug}.{$ext}");

            if (is_file($path)) {
                return [
                    'path'     => $path,
                    'filename' => "{$slug}.{$ext}",
                ];
            }
        }

        return null;
    }
}
