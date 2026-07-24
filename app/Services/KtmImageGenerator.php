<?php

namespace App\Services;

use App\Models\Mahasiswa;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

class KtmImageGenerator
{
    public const SETTING_UNIV_NAME = 'app_univ_name';

    public const SETTING_UNIV_LOGO = 'app_univ_logo';

    public function __construct(
        private ImageManager $imageManager
    ) {}

    public static function makeDefault(): self
    {
        return new self(ImageManager::gd());
    }

    /**
     * Render file KTM ke storage public, kembalikan path relatif (mis. ktm/xxx.png).
     *
     * @param  string  $templatePathRelatif  Path di disk public (dari setting ktm_template)
     */
    public function generateToStorage(Mahasiswa $m, string $templatePathRelatif): string
    {
        $m->loadMissing(['prodi.jenjang']);

        $absTemplate = Storage::disk('public')->path($templatePathRelatif);
        if (! is_file($absTemplate)) {
            throw new RuntimeException('Berkas template KTM tidak ditemukan di storage.');
        }

        $image = $this->imageManager->read($absTemplate);
        $w = $image->width();
        $h = $image->height();

        $minSide = min($w, $h);
        $layout = config('ktm.layout', []);
        $fontBold = $this->resolveFontBold();
        $fontReg = $this->resolveFontRegular() ?? $fontBold;

        $this->placeUnivLogoIfAny($image, $w, $h, $layout);

        $dataX = $this->placeStudentFoto($image, $m, $w, $h, $layout);

        $univName = $this->universityNameFromSettings();

        $this->drawTextBlock(
            $image,
            (string) config('ktm.title', 'Kartu Tanda Mahasiswa'),
            (int) ($w * (float) ($layout['header_anchor_x'] ?? 0.94)),
            (int) ($h * (float) ($layout['header_title_y'] ?? 0.09)),
            (float) ($layout['header_title_size'] ?? 0.028) * $minSide,
            $fontReg,
            'right',
        );

        $this->drawTextBlock(
            $image,
            $univName,
            (int) ($w * (float) ($layout['header_anchor_x'] ?? 0.94)),
            (int) ($h * (float) ($layout['header_univ_y'] ?? 0.145)),
            (float) ($layout['header_univ_size'] ?? 0.04) * $minSide,
            $fontBold,
            'right',
        );

        $nim = strtoupper((string) ($m->nim ?? '—'));
        $nama = strtoupper((string) ($m->nama ?? '—'));
        $prodiStr = '—';
        if ($m->prodi) {
            $jen = $m->prodi->jenjang;
            $j = $jen ? (string) ($jen->kode ?? $jen->nama ?? '') : '';
            $prodiStr = $j !== '' ? strtoupper($m->prodi->nama).' ('.$j.')' : strtoupper($m->prodi->nama);
        }

        $lineSize = (float) ($layout['data_line_size'] ?? 0.032) * $minSide;
        $y = (int) ($h * (float) ($layout['data_start_y'] ?? 0.5));
        $rightMargin = (int) max(4, $w * 0.03);
        $dataMaxW = (int) max(60, $w - $dataX - $rightMargin);
        $lineHeight = (float) ($layout['data_text_line_height'] ?? 1.32);
        /* Jeda antarblok (NIM → NAMA → PRODI) vs tinggi piksel */
        $blockGap = (float) ($layout['data_line_gap'] ?? 0.1) * $h;
        $charsPerLine = max(12, (int) ($dataMaxW / max(0.1, $lineSize * 0.48)));

        $lines = [
            $this->formatKtmLine('NIM', $nim),
            $this->formatKtmLine('NAMA', $nama),
            $this->formatKtmLine('PRODI', $prodiStr),
        ];

        foreach ($lines as $line) {
            $wrapped = $this->mbWordWrapKtm($line, $charsPerLine);
            $numVisualLines = substr_count($wrapped, "\n") + 1;
            $this->drawTextBlock(
                $image,
                $wrapped,
                $dataX,
                $y,
                $lineSize,
                $fontBold,
                'left',
                $lineHeight,
            );
            $y += (int) ($lineSize * $lineHeight * $numVisualLines + $blockGap);
        }

        $filename = 'ktm/ktm_'.(int) $m->id.'_'.date('Ymd_His').'.png';
        $outAbs = Storage::disk('public')->path($filename);

        $dir = dirname($outAbs);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Tidak dapat membuat direktori output KTM.');
        }

        try {
            $image->save($outAbs);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal menyimpan gambar KTM: '.$e->getMessage(), 0, $e);
        }

        return $filename;
    }

    private function universityNameFromSettings(): string
    {
        $v = trim((string) (Setting::query()
            ->where('key', self::SETTING_UNIV_NAME)
            ->value('value') ?? ''));

        if ($v !== '') {
            return $v;
        }

        return (string) config('ktm.university_name', 'Universitas');
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function placeUnivLogoIfAny(ImageInterface $image, int $w, int $h, array $layout): void
    {
        $raw = Setting::query()
            ->where('key', self::SETTING_UNIV_LOGO)
            ->value('value');
        $abs = $this->resolveUnivLogoPath($raw !== null ? (string) $raw : null);
        if ($abs === null) {
            return;
        }

        try {
            $logo = $this->imageManager->read($abs);
        } catch (Throwable) {
            return;
        }

        $maxH = (int) max(1, $h * (float) ($layout['logo_max_height_ratio'] ?? 0.2));
        $logo = $logo->scaleDown(height: $maxH);

        $ox = (int) max(0, $w * (float) ($layout['logo_offset_x'] ?? 0.035));
        $oy = (int) max(0, $h * (float) ($layout['logo_offset_y'] ?? 0.04));

        $image->place($logo, 'top-left', $ox, $oy);
    }

    /**
     * Tempel foto mahasiswa (atau placeholder) di kiri. Mengembalikan posisi x (piksel) awal teks NIM/NAMA/PRODI.
     *
     * @param  array<string, mixed>  $layout
     */
    private function placeStudentFoto(ImageInterface $canvas, Mahasiswa $m, int $w, int $h, array $layout): int
    {
        $px = (int) max(0, $w * (float) ($layout['photo_x'] ?? 0.04));
        $py = (int) max(0, $h * (float) ($layout['photo_y'] ?? 0.22));
        $boxW = (int) max(32, $w * (float) ($layout['photo_width_ratio'] ?? 0.26));
        $boxH = (int) max(48, $h * (float) ($layout['photo_height_ratio'] ?? 0.5));
        $gap = (int) max(0, $w * (float) ($layout['data_gap_from_photo'] ?? 0.03));

        $foto = null;
        $path = $this->resolveMhsFotoPath($m->foto);
        $coverPos = (string) ($layout['photo_cover_position'] ?? 'top');
        if ($path !== null) {
            try {
                $foto = $this->imageManager->read($path);
                /* `top` = crop dari atas (kepala tidak kepotong); tengah horizontal via Intervention */
                $foto = $foto->cover($boxW, $boxH, $coverPos);
            } catch (Throwable) {
                $foto = null;
            }
        }
        if ($foto === null) {
            $foto = $this->makePlaceholderFotoImage($boxW, $boxH);
        }

        $canvas->place($foto, 'top-left', $px, $py);

        return (int) ($px + $boxW + $gap);
    }

    private function makePlaceholderFotoImage(int $boxW, int $boxH): ImageInterface
    {
        $img = $this->imageManager->create($boxW, $boxH);
        $img->fill('e2e8f0');
        $cx = (int) ($boxW / 2);
        $r = (int) max(5, (int) (min($boxW, $boxH) * 0.13));
        $img->drawCircle($cx, (int) ($boxH * 0.3), function ($c) use ($r) {
            $c->radius($r);
            $c->background('94a3b8');
        });
        $img->drawEllipse($cx, (int) ($boxH * 0.58), function ($e) use ($boxW, $boxH) {
            $e->size((int) max(20, $boxW * 0.5), (int) max(20, $boxH * 0.28));
            $e->background('94a3b8');
        });

        return $img;
    }

    private function resolveMhsFotoPath(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $v = trim($value);
        if (str_starts_with($v, 'data:image')) {
            return null;
        }

        $rel = null;
        if (preg_match('~/storage/([^?\s#]+)~', $v, $m)) {
            $rel = $m[1];
        } elseif (! str_contains($v, '://')) {
            $rel = ltrim($v, '/');
        } else {
            return null;
        }

        if ($rel === null || $rel === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($rel)) {
            return null;
        }

        $abs = Storage::disk('public')->path($rel);

        return is_readable($abs) ? $abs : null;
    }

    private function resolveUnivLogoPath(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $v = trim($value);
        if (str_starts_with($v, 'data:image')) {
            return null;
        }

        $rel = null;
        if (preg_match('~/storage/([^?\s#]+)~', $v, $m)) {
            $rel = $m[1];
        } elseif (! str_contains($v, '://')) {
            $rel = ltrim($v, '/');
        } else {
            return null;
        }

        if ($rel === null || $rel === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($rel)) {
            return null;
        }

        $abs = Storage::disk('public')->path($rel);

        return is_readable($abs) ? $abs : null;
    }

    private function formatKtmLine(string $label, string $value): string
    {
        return str_pad($label, 5).' : '.mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Pisah teks multi-kata (UTF-8) agar muat; perkiraan lebar = ~0.48 × font size per karakter.
     */
    private function mbWordWrapKtm(string $str, int $maxChars): string
    {
        if ($maxChars < 6 || $str === '') {
            return $str;
        }

        $all = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $str)) as $sub) {
            if (mb_strlen($sub) <= $maxChars) {
                $all[] = $sub;

                continue;
            }
            $line = '';
            $words = preg_split('/\s+/u', $sub, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $word) {
                if (mb_strlen($word) > $maxChars) {
                    if ($line !== '') {
                        $all[] = $line;
                        $line = '';
                    }
                    for ($i = 0, $L = mb_strlen($word); $i < $L; $i += $maxChars) {
                        $all[] = mb_substr($word, $i, $maxChars);
                    }

                    continue;
                }
                $try = $line === '' ? $word : $line.' '.$word;
                if (mb_strlen($try) > $maxChars) {
                    $all[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            if ($line !== '') {
                $all[] = $line;
            }
        }

        return implode("\n", $all);
    }

    private function drawTextBlock(
        ImageInterface $image,
        string $text,
        int $x,
        int $y,
        float $size,
        string $fontPath,
        string $align,
        ?float $lineHeight = null
    ): void {
        $image->text($text, $x, $y, function ($font) use ($size, $fontPath, $align, $lineHeight) {
            $font->file($fontPath);
            $font->size($size);
            $font->color('000000');
            $font->align($align);
            $font->valign('top');
            if ($lineHeight !== null) {
                $font->lineHeight($lineHeight);
            }
        });
    }

    private function resolveFontBold(): string
    {
        $candidates = array_filter(array_unique(array_merge(
            $this->configuredPaths('font_bold'),
            $this->defaultFontCandidates('bold')
        )));

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            'Font tebal untuk KTM tidak ditemukan. Pasang font TTF (mis. fonts-dejavu di server) '.
            'atau atur KTM_FONT_BOLD di .env, atau letakkan DejaVuSans-Bold.ttf di resources/fonts/.'
        );
    }

    private function resolveFontRegular(): ?string
    {
        $candidates = array_filter(array_unique(array_merge(
            $this->configuredPaths('font_regular'),
            $this->defaultFontCandidates('regular')
        )));

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function configuredPaths(string $key): array
    {
        $p = config('ktm.'.$key);
        if (is_string($p) && $p !== '') {
            return [$p];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function defaultFontCandidates(string $weight): array
    {
        $candidates = match ($weight) {
            'bold' => [
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                resource_path('fonts/NotoSans-Bold.ttf'),
            ],
            default => [
                resource_path('fonts/DejaVuSans.ttf'),
                resource_path('fonts/NotoSans-Regular.ttf'),
            ],
        };

        $candidates[] = '/usr/share/fonts/truetype/dejavu/DejaVuSans'.($weight === 'bold' ? '-Bold' : '').'.ttf';
        $candidates[] = '/usr/share/fonts/dejavu/DejaVuSans'.($weight === 'bold' ? '-Bold' : '').'.ttf';
        $candidates[] = '/usr/share/fonts/TTF/DejaVuSans'.($weight === 'bold' ? '-Bold' : '').'.ttf';

        if (PHP_OS_FAMILY === 'Darwin') {
            $candidates[] = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
            if ($weight !== 'bold') {
                $candidates[] = '/System/Library/Fonts/Supplemental/Arial.ttf';
            }
        }

        return $candidates;
    }
}
