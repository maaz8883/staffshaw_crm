<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** Teams allowed to use this brand in sales/invoices/etc. */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'brand_team')->withTimestamps();
    }

    /**
     * True when the logo is mostly white / very light (made for dark backgrounds), so it
     * would vanish on a white invoice and needs a dark plate behind it. Cached per file.
     */
    public function logoIsLight(): bool
    {
        $path = $this->image ? public_path('storage/'.$this->image) : null;
        if (! $path || ! is_file($path)) {
            return false;
        }

        return \Illuminate\Support\Facades\Cache::rememberForever(
            'brand-logo-light.'.md5($path.'|'.filemtime($path)),
            fn () => self::imageIsLight($path)
        );
    }

    /** Share of visible pixels that are near-white (light grey / white text, outlines). */
    public static function imageIsLight(string $path): bool
    {
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $img) {
            return false;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(sqrt(($w * $h) / 40000))); // ~40k samples at most
        $visible = 0;
        $light = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $alpha = ($c >> 24) & 0x7F; // 0 = opaque, 127 = transparent
                if ($alpha > 90) {
                    continue;
                }
                $visible++;
                $lum = 0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF);
                if ($lum >= 215) {
                    $light++;
                }
            }
        }
        imagedestroy($img);

        // Logos with a solid white background have almost everything "light" but no
        // transparency: those already work on white paper.
        $hasTransparency = $visible < (int) ceil($w / $step) * (int) ceil($h / $step) * 0.98;

        return $hasTransparency && $visible > 0 && ($light / $visible) >= 0.30;
    }
}
