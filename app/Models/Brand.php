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

    /**
     * Logo with its empty transparent margins cut off (many uploads have lots of padding, so the
     * logo looks tiny). Saved once as a PNG next to the original and reused; falls back to the
     * original file. Returns a path relative to the public storage disk.
     */
    public function trimmedLogo(): ?string
    {
        $src = $this->image ? public_path('storage/'.$this->image) : null;
        if (! $src || ! is_file($src)) {
            return null;
        }
        $rel = 'brands/_trimmed/'.md5($this->image.'|'.filemtime($src)).'.png';
        $dest = storage_path('app/public/'.$rel);
        if (is_file($dest)) {
            return $rel;
        }

        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($src)) : false;
        if (! $img) {
            return $this->image;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 110) { // visible pixel
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }
        // Nothing to trim (no transparency, or already tight): keep the original
        if ($maxX < 0 || ($minX <= 1 && $minY <= 1 && $maxX >= $w - 2 && $maxY >= $h - 2)) {
            imagedestroy($img);

            return $this->image;
        }
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $img, 0, 0, $minX, $minY, $cw, $ch);
        if (! is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0775, true);
        }
        $ok = @imagepng($out, $dest, 6);
        imagedestroy($img);
        imagedestroy($out);

        return $ok ? $rel : $this->image;
    }

    /**
     * Decide from the logo's pixels (sampled on a grid of ~40k cells):
     * only white / black areas that touch the transparent background count — white inside
     * a black box (e.g. a "T" in a black square) is fine on white paper and is ignored.
     */
    public static function imageBackground(string $path): ?string
    {
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(sqrt(($w * $h) / 40000)));
        $cols = (int) ceil($w / $step);
        $rows = (int) ceil($h / $step);

        // 0 = transparent, 1 = near-white, 2 = near-black, 3 = anything else
        $grid = [];
        $visible = 0;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $px = imagecolorat($img, min($w - 1, $c * $step), min($h - 1, $r * $step));
                if ((($px >> 24) & 0x7F) > 90) {
                    $grid[$r * $cols + $c] = 0;

                    continue;
                }
                $visible++;
                $lum = 0.2126 * (($px >> 16) & 0xFF) + 0.7152 * (($px >> 8) & 0xFF) + 0.0722 * ($px & 0xFF);
                $grid[$r * $cols + $c] = $lum >= 215 ? 1 : ($lum <= 70 ? 2 : 3);
            }
        }
        imagedestroy($img);

        // A solid background (no transparency) already looks right on white paper
        if ($visible === 0 || $visible >= $rows * $cols * 0.98) {
            return null;
        }

        $lightShare = self::exposedCells($grid, $rows, $cols, 1) / $visible;
        $darkShare = self::exposedCells($grid, $rows, $cols, 2) / $visible;
        if ($lightShare < 0.10) {
            return null; // nothing white would disappear on white paper
        }

        return $darkShare >= 0.10 ? 'mid' : 'dark';
    }

    /** Cells of one tone that belong to a region touching transparency (or the image edge). */
    private static function exposedCells(array $grid, int $rows, int $cols, int $tone): int
    {
        $seen = [];
        $total = 0;
        foreach ($grid as $start => $t) {
            if ($t !== $tone || isset($seen[$start])) {
                continue;
            }
            $queue = [$start];
            $seen[$start] = true;
            $size = 0;
            $exposed = false;
            while ($queue) {
                $i = array_pop($queue);
                $size++;
                $r = intdiv($i, $cols);
                $c = $i % $cols;
                foreach ([[$r - 1, $c], [$r + 1, $c], [$r, $c - 1], [$r, $c + 1]] as [$nr, $nc]) {
                    if ($nr < 0 || $nc < 0 || $nr >= $rows || $nc >= $cols) {
                        $exposed = true;

                        continue;
                    }
                    $j = $nr * $cols + $nc;
                    if ($grid[$j] === 0) {
                        $exposed = true;
                    } elseif ($grid[$j] === $tone && ! isset($seen[$j])) {
                        $seen[$j] = true;
                        $queue[] = $j;
                    }
                }
            }
            if ($exposed) {
                $total += $size;
            }
        }

        return $total;
    }
}
