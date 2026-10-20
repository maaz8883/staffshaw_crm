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
     * Background the logo needs to be readable on a white invoice (cached per file):
     *  - null    : fine on white (dark / colourful logos)
     *  - 'dark'  : mostly white / very light parts (made for dark sites)
     *  - 'mid'   : both black and white parts (e.g. black "amazing" + white "publishers") — a
     *              mid-grey plate keeps both readable
     */
    public function logoBackground(): ?string
    {
        $path = $this->image ? public_path('storage/'.$this->image) : null;
        if (! $path || ! is_file($path)) {
            return null;
        }

        $v = \Illuminate\Support\Facades\Cache::rememberForever(
            'brand-logo-bg.'.md5($path.'|'.filemtime($path)),
            fn () => self::imageBackground($path) ?? 'none'
        );

        return in_array($v, ['dark', 'mid'], true) ? $v : null;
    }

    /** True when the logo needs any plate (kept for older callers). */
    public function logoIsLight(): bool
    {
        return $this->logoBackground() !== null;
    }

    /** Decide from the share of visible pixels that are near-white and near-black. */
    public static function imageBackground(string $path): ?string
    {
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(sqrt(($w * $h) / 40000))); // ~40k samples at most
        $samples = 0;
        $visible = 0;
        $light = 0;
        $dark = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $samples++;
                $c = imagecolorat($img, $x, $y);
                if ((($c >> 24) & 0x7F) > 90) { // mostly transparent
                    continue;
                }
                $visible++;
                $lum = 0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF);
                if ($lum >= 215) {
                    $light++;
                } elseif ($lum <= 70) {
                    $dark++;
                }
            }
        }
        imagedestroy($img);

        // A solid white background (no transparency) already looks right on white paper
        if ($visible === 0 || $visible >= $samples * 0.98) {
            return null;
        }
        $lightShare = $light / $visible;
        $darkShare = $dark / $visible;
        if ($lightShare < 0.10) {
            return null;
        }

        return $darkShare >= 0.10 ? 'mid' : 'dark';
    }
}
