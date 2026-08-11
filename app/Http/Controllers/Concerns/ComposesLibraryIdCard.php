<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PublicAssetPath;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;

/**
 * Student Library ID cards — GGCAST front/back templates at
 * public/images/id_templates/{front|back}.png (~1989×3360 / 2520×3360).
 */
trait ComposesLibraryIdCard
{
    protected function idCardTemplate(string $side)
    {
        $relative = "images/id_templates/{$side}.png";
        $path = PublicAssetPath::resolve($relative)
            ?? (is_file(public_path($relative)) ? public_path($relative) : null)
            ?? base_path("images/id_templates/{$side}.png");

        if ($path === null || ! is_file($path)) {
            abort(500, "Student ID {$side} template not found at public/{$relative}.");
        }

        return Image::make($path);
    }

    protected function studentIdPngResponse($img)
    {
        return $img->response('png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function studentFontPath(bool $preferBold = false): string
    {
        $bold = public_path('fonts/arialbd.ttf');
        if ($preferBold && is_file($bold)) {
            return $bold;
        }

        return public_path('fonts/arial.ttf');
    }

    protected function drawStudentIdText(
        $img,
        string $text,
        int $x,
        int $y,
        int $size,
        string $color = '#111111',
        string $align = 'left',
        string $valign = 'middle',
        bool $bold = false
    ): void {
        $fontPath = $this->studentFontPath($bold);
        if (! is_file($fontPath) || $text === '') {
            return;
        }

        $offsets = ($bold && ! is_file(public_path('fonts/arialbd.ttf')))
            ? [[-2, 0], [2, 0], [0, -2], [0, 2], [-1, -1], [-1, 1], [1, -1], [1, 1], [0, 0]]
            : [[0, 0]];

        foreach ($offsets as [$ox, $oy]) {
            $img->text($text, $x + $ox, $y + $oy, function ($font) use ($fontPath, $size, $color, $align, $valign) {
                $font->file($fontPath);
                $font->size($size);
                $font->color($color);
                $font->align($align);
                $font->valign($valign);
            });
        }
    }

    protected function fitStudentIdText(string $text, int $maxSize, int $minSize, int $maxWidth): int
    {
        $size = $maxSize;
        while ($size > $minSize) {
            $approx = (int) (strlen($text) * $size * 0.55);
            if ($approx <= $maxWidth) {
                return $size;
            }
            $size -= 4;
        }

        return $minSize;
    }

    /**
     * Course / program label for pink bar — keep short codes as written.
     */
    protected function formatStudentProgramLabel(?string $course): string
    {
        $course = trim(preg_replace('/\s+/u', ' ', (string) $course) ?? '');
        if ($course === '') {
            return '';
        }
        if (mb_strlen($course) <= 30) {
            return mb_strtoupper($course);
        }

        $stop = ['of', 'in', 'and', 'the', 'for', 'to', 'a', 'an', 'at', 'on', 'major', 'with'];
        $words = preg_split('/\s+/u', $course) ?: [];
        $abbr = '';
        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/u', '', $word) ?? '';
            if ($clean === '' || in_array(strtolower($clean), $stop, true)) {
                continue;
            }
            $abbr .= mb_strtoupper(mb_substr($clean, 0, 1));
        }

        return $abbr !== '' ? $abbr : mb_strtoupper(mb_substr($course, 0, 16));
    }

    protected function drawStudentProgramBar($img, string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        $fontPath = $this->studentFontPath(true);
        if (! is_file($fontPath)) {
            return;
        }

        $pinkLeft = 0;
        $pinkRight = 454;
        $pinkTop = 820;
        $pinkBottom = 2695;
        $pinkCenterX = (int) (($pinkLeft + $pinkRight) / 2);
        $pinkCenterY = (int) (($pinkTop + $pinkBottom) / 2);
        $maxTextLen = $pinkBottom - $pinkTop - 60;

        $size = $this->fitStudentIdText($label, 110, 42, $maxTextLen);
        $bbox = imagettfbbox($size, 0, $fontPath, $label);
        if ($bbox === false) {
            return;
        }

        $textW = abs($bbox[2] - $bbox[0]) + 32;
        $textH = abs($bbox[7] - $bbox[1]) + 32;
        $src = imagecreatetruecolor(max(1, $textW), max(1, $textH));
        imagesavealpha($src, true);
        $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
        imagefill($src, 0, 0, $transparent);
        $white = imagecolorallocate($src, 255, 255, 255);

        $baseX = 16 - (int) $bbox[0];
        $baseY = 16 - (int) $bbox[7];
        foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [-1, -1], [1, 1], [0, 0]] as [$ox, $oy]) {
            imagettftext($src, $size, 0, $baseX + $ox, $baseY + $oy, $white, $fontPath, $label);
        }

        $rotated = imagerotate($src, 90, $transparent);
        imagesavealpha($rotated, true);
        imagedestroy($src);

        $rotated = $this->cropTransparentGdStudent($rotated);
        if ($rotated === null) {
            return;
        }

        $rotW = imagesx($rotated);
        $rotH = imagesy($rotated);
        $x = max($pinkLeft + 4, min((int) ($pinkCenterX - ($rotW / 2)), $pinkRight - $rotW - 4));
        $y = max($pinkTop, min((int) ($pinkCenterY - ($rotH / 2)), $pinkBottom - $rotH));

        $tmpPath = storage_path('app/stu_program_bar_'.uniqid('', true).'.png');
        imagepng($rotated, $tmpPath);
        imagedestroy($rotated);

        $img->insert(Image::make($tmpPath), 'top-left', $x, $y);
        @unlink($tmpPath);
    }

    /**
     * @param  \GdImage|resource  $im
     * @return \GdImage|resource|null
     */
    protected function cropTransparentGdStudent($im)
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha < 120) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($im);

            return null;
        }

        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        $cropped = imagecreatetruecolor($cw, $ch);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefill($cropped, 0, 0, $transparent);
        imagecopy($cropped, $im, 0, 0, $minX, $minY, $cw, $ch);
        imagedestroy($im);

        return $cropped;
    }

    protected function prepareStudentIdPhoto(string $photoPath, int $width, int $height)
    {
        $pad = 8;
        $innerW = max(1, $width - ($pad * 2));
        $innerH = max(1, $height - ($pad * 2));

        $photo = Image::make($photoPath);
        if (method_exists($photo, 'orientate')) {
            $photo->orientate();
        }

        // Top-biased crop: keep heads visible across mixed upload aspect ratios
        $photo->fit($innerW, $innerH, function () {
        }, 'top');

        $canvas = Image::canvas($width, $height, '#ffffff');
        $canvas->insert($photo, 'top-left', $pad, $pad);

        return $canvas;
    }

    /**
     * PNG QR without Imagick.
     */
    protected function makeStudentQrPng(string $content, int $pixelSize = 420): string
    {
        $qr = \BaconQrCode\Encoder\Encoder::encode(
            $content,
            \BaconQrCode\Common\ErrorCorrectionLevel::M()
        );
        $matrix = $qr->getMatrix();
        $modules = $matrix->getWidth();
        $margin = 2;
        $total = $modules + ($margin * 2);
        $scale = max(1, (int) floor($pixelSize / $total));
        $imgSize = $total * $scale;

        $im = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $imgSize - 1, $imgSize - 1, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 0) {
                    $x1 = ($x + $margin) * $scale;
                    $y1 = ($y + $margin) * $scale;
                    imagefilledrectangle($im, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
                }
            }
        }

        if ($imgSize !== $pixelSize) {
            $resized = imagecreatetruecolor($pixelSize, $pixelSize);
            $rWhite = imagecolorallocate($resized, 255, 255, 255);
            imagefilledrectangle($resized, 0, 0, $pixelSize - 1, $pixelSize - 1, $rWhite);
            imagecopyresampled($resized, $im, 0, 0, 0, 0, $pixelSize, $pixelSize, $imgSize, $imgSize);
            imagedestroy($im);
            $im = $resized;
        }

        $path = storage_path('app/stu_qr_'.uniqid('', true).'.png');
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * @param  array{
     *     photo:?string,
     *     full_name:string,
     *     program_label:?string,
     *     qrcode:?string,
     *     id_number:?string
     * }  $data
     */
    protected function composeIdCardFront($img, array $data)
    {
        // Photo frame on new GGCAST student front
        $photoX = 1145;
        $photoY = 825;
        $photoW = 700;
        $photoH = 900;

        $photoPath = PublicAssetPath::resolve($data['photo'] ?? null);
        if ($photoPath) {
            $profile = $this->prepareStudentIdPhoto($photoPath, $photoW, $photoH);
            $img->insert($profile, 'top-left', $photoX, $photoY);
        }

        if (! empty($data['program_label'])) {
            $this->drawStudentProgramBar($img, (string) $data['program_label']);
        }

        if (! empty($data['qrcode'])) {
            $qrPath = $this->makeStudentQrPng((string) $data['qrcode'], 420);
            $img->insert(Image::make($qrPath), 'top-left', 540, 980);
            @unlink($qrPath);
        }

        if (! empty($data['id_number'])) {
            $idNumber = trim((string) $data['id_number']);
            $idSize = $this->fitStudentIdText($idNumber, 72, 40, 480);
            $this->drawStudentIdText($img, $idNumber, 750, 1450, $idSize, '#111111', 'center', 'middle', true);
        }

        $name = trim($data['full_name'] ?? '');
        if ($name !== '') {
            $display = mb_strtoupper($name);
            $nameSize = $this->fitStudentIdText($display, 140, 70, 1250);
            $this->drawStudentIdText($img, $display, 1188, 2115, $nameSize, '#111111', 'center', 'middle', true);
        }

        return $img;
    }

    /**
     * Normalize year level 1–4 from DB values like 1, "1", "1st", "First Year", "IV".
     */
    protected function resolveStudentYearLevel(?string $year): ?int
    {
        if ($year === null || trim($year) === '') {
            return null;
        }
        $y = strtolower(trim($year));

        // Match ordinals first (1st / 2nd / 3rd / 4th) then bare digits / words
        if (preg_match('/\b(4th|fourth|iv|senior|year\s*4)\b/', $y) || preg_match('/^4$/', $y)) {
            return 4;
        }
        if (preg_match('/\b(3rd|third|iii|junior|year\s*3)\b/', $y) || preg_match('/^3$/', $y)) {
            return 3;
        }
        if (preg_match('/\b(2nd|second|ii|sophomore|year\s*2)\b/', $y) || preg_match('/^2$/', $y)) {
            return 2;
        }
        if (preg_match('/\b(1st|first|freshman|year\s*1)\b/', $y) || preg_match('/^1$/', $y)) {
            return 1;
        }
        if (preg_match('/\b([1-4])\b/', $y, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array{
     *     birth_date:?string,
     *     blood_type:?string,
     *     sex:?string,
     *     year:?string,
     *     emergency_person:?string,
     *     emergency_relationship:?string,
     *     emergency_number:?string,
     *     emergency_address:?string,
     *     signature:?string
     * }  $data
     */
    protected function composeIdCardBack($img, array $data)
    {
        $valueColor = '#111111';
        $valueSize = 70;

        // Date of Birth
        if (! empty($data['birth_date'])) {
            try {
                $dob = Carbon::parse($data['birth_date'])
                    ->timezone(config('app.timezone', 'Asia/Manila'))
                    ->format('F d, Y');
            } catch (\Throwable $e) {
                $dob = (string) $data['birth_date'];
            }
            $this->drawStudentIdText($img, $dob, 820, 214, $valueSize, $valueColor, 'left', 'middle', true);
        }

        // Blood type / sex — only when present on the record
        if (! empty($data['blood_type'])) {
            $this->drawStudentIdText($img, trim((string) $data['blood_type']), 820, 442, $valueSize, $valueColor, 'left', 'middle', true);
        }
        if (! empty($data['sex'])) {
            $this->drawStudentIdText($img, trim((string) $data['sex']), 1860, 442, $valueSize, $valueColor, 'left', 'middle', true);
        }

        // Stamp validation mark on the matching year row
        $level = $this->resolveStudentYearLevel($data['year'] ?? null);
        if ($level !== null) {
            // FIRST YEAR y≈1580, SECOND≈1803, THIRD≈2031, FOURTH≈2258 (band centers)
            $yearCenters = [1 => 1580, 2 => 1803, 3 => 2031, 4 => 2258];
            $y = $yearCenters[$level];
            $stamp = 'VALIDATED';
            $this->drawStudentIdText($img, $stamp, 1680, $y, 64, '#065624', 'center', 'middle', true);
        }

        // Emergency notify box (large white region under year rows)
        $emLines = array_values(array_filter([
            trim((string) ($data['emergency_person'] ?? '')),
            trim((string) ($data['emergency_relationship'] ?? '')),
            trim((string) ($data['emergency_number'] ?? '')),
            trim((string) ($data['emergency_address'] ?? '')),
        ], fn ($v) => $v !== ''));

        $emY = 2650;
        foreach ($emLines as $line) {
            $size = $this->fitStudentIdText($line, 58, 34, 2100);
            $this->drawStudentIdText($img, $line, 1260, $emY, $size, $valueColor, 'center', 'middle', true);
            $emY += 65;
        }

        // Signature on thin white bar above "Student's Signature"
        $signaturePath = PublicAssetPath::resolve($data['signature'] ?? null);
        if ($signaturePath) {
            $signature = Image::make($signaturePath)->resize(900, 260, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            });
            $img->insert($signature, 'top-left', 810, 3015);
        }

        return $img;
    }
}
