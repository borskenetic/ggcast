<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PublicAssetPath;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;

/**
 * Faculty/staff Library ID — personnel front/back templates.
 * Front layout matches student-style card: pink program bar, QR, ID, photo, name.
 */
trait ComposesEmployeeIdCard
{
    protected function employeeIdCardTemplate(string $side)
    {
        $name = $side === 'back' ? 'back_employee' : 'front_employee';
        $relative = "images/id_templates/{$name}.png";

        $path = PublicAssetPath::resolve($relative)
            ?? (is_file(public_path($relative)) ? public_path($relative) : null)
            ?? (is_file(base_path('public/'.$relative)) ? base_path('public/'.$relative) : null);

        if ($path === null || ! is_file($path)) {
            abort(500, "Employee ID {$side} template not found at public/{$relative}. Deploy public/images/id_templates/{$name}.png.");
        }

        return Image::make($path);
    }

    /**
     * PNG response that avoids browser/proxy caching of regenerated ID cards.
     */
    protected function employeeIdPngResponse($img)
    {
        return $img->response('png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function employeeFontPath(bool $preferBold = false): string
    {
        $bold = public_path('fonts/arialbd.ttf');
        if ($preferBold && is_file($bold)) {
            return $bold;
        }

        return public_path('fonts/arial.ttf');
    }

    protected function drawEmployeeIdText(
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
        $fontPath = $this->employeeFontPath($bold);
        if (! is_file($fontPath) || $text === '') {
            return;
        }

        // Fake bold via stroke if dedicated bold font is missing.
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

    protected function fitEmployeeIdText(string $text, int $maxSize, int $minSize, int $maxWidth): int
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
     * Label for the pink side bar: keep the stored program text whenever it fits.
     * Only shorten very long formal titles so the vertical bar stays readable.
     */
    protected function formatEmployeeProgramLabel(?string $program, ?string $department = null, ?string $position = null): string
    {
        foreach ([$program, $department, $position] as $candidate) {
            $candidate = trim(preg_replace('/\s+/u', ' ', (string) $candidate) ?? '');
            if ($candidate === '') {
                continue;
            }

            // Keep short / mid-length labels as-is (e.g. "BSED MATH", "BSCS", "BEED", "AB English").
            // Do not force letter-acronyms for every program.
            if (mb_strlen($candidate) <= 30) {
                return mb_strtoupper($candidate);
            }

            // Very long formal names only: use light abbreviation so the bar is not overcrowded.
            return $this->abbreviateLongProgramName($candidate);
        }

        return '';
    }

    /**
     * Light acronym for long formal program titles only.
     * "Bachelor of Science in Computer Science" → "BSCS"
     */
    protected function abbreviateLongProgramName(string $name): string
    {
        $stop = ['of', 'in', 'and', 'the', 'for', 'to', 'a', 'an', 'at', 'on', 'major', 'with'];
        $words = preg_split('/\s+/u', $name) ?: [];
        $abbr = '';
        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/u', '', $word) ?? '';
            if ($clean === '' || in_array(strtolower($clean), $stop, true)) {
                continue;
            }
            $abbr .= mb_strtoupper(mb_substr($clean, 0, 1));
        }

        return $abbr !== '' ? $abbr : mb_strtoupper(mb_substr($name, 0, 16));
    }

    /**
     * Draw vertical program text centered on the pink left stripe (reads bottom → top).
     */
    protected function drawEmployeeProgramBar($img, string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        $fontPath = $this->employeeFontPath(true);
        if (! is_file($fontPath)) {
            return;
        }

        // Pink stripe usable area (template ~1988×3360), measured from front_employee.png.
        $pinkLeft = 0;
        $pinkRight = 455;
        $pinkTop = 820;
        $pinkBottom = 2698;
        $pinkCenterX = (int) (($pinkLeft + $pinkRight) / 2);
        $pinkCenterY = (int) (($pinkTop + $pinkBottom) / 2);
        $maxTextLen = $pinkBottom - $pinkTop - 60; // max height after rotation

        // Prefer full label; only scale the font down for longer text.
        $size = $this->fitEmployeeIdText($label, 110, 42, $maxTextLen);
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

        // Crop transparent padding from imagerotate so centering is accurate.
        $rotated = $this->cropTransparentGd($rotated);
        if ($rotated === null) {
            return;
        }

        $rotW = imagesx($rotated);
        $rotH = imagesy($rotated);
        $x = (int) ($pinkCenterX - ($rotW / 2));
        $y = (int) ($pinkCenterY - ($rotH / 2));
        // Keep fully inside pink band
        $x = max($pinkLeft + 4, min($x, $pinkRight - $rotW - 4));
        $y = max($pinkTop, min($y, $pinkBottom - $rotH));

        $tmpPath = storage_path('app/emp_program_bar_'.uniqid('', true).'.png');
        imagepng($rotated, $tmpPath);
        imagedestroy($rotated);

        $bar = Image::make($tmpPath);
        $img->insert($bar, 'top-left', $x, $y);
        @unlink($tmpPath);
    }

    /**
     * Crop fully transparent edges of a truecolor GD image. Returns null if empty.
     *
     * @param  \GdImage|resource  $im
     * @return \GdImage|resource|null
     */
    protected function cropTransparentGd($im)
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

    /**
     * Build a QR PNG with GD (no Imagick). Returns absolute path to a temp PNG.
     */
    protected function makeEmployeeQrPng(string $content, int $pixelSize = 420): string
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
                // Bacon matrix: non-zero modules are dark
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

        $path = storage_path('app/emp_qr_'.uniqid('', true).'.png');
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * Force any uploaded photo into the fixed ID frame size.
     * Mixed aspect ratios are normalized; crop is biased to the TOP so heads
     * stay in frame (center-crop was cutting off hair/forehead).
     * EXIF orientation is applied so phone photos rotate correctly.
     */
    protected function prepareEmployeeIdPhoto(string $photoPath, int $width, int $height)
    {
        // Small inner pad so hair is not clipped by the green border edge.
        $pad = 8;
        $innerW = max(1, $width - ($pad * 2));
        $innerH = max(1, $height - ($pad * 2));

        $photo = Image::make($photoPath);
        if (method_exists($photo, 'orientate')) {
            $photo->orientate();
        }

        // Force fill of the target box; never center-crop (that chops heads).
        $photo->fit($innerW, $innerH, function ($constraint) {
            // Allow upscale so tiny uploads still fill the frame.
        }, 'top');

        $canvas = Image::canvas($width, $height, '#ffffff');
        $canvas->insert($photo, 'top-left', $pad, $pad);

        return $canvas;
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
    protected function composeEmployeeIdCardFront($img, array $data)
    {
        // Fixed photo frame on front_employee.png @ ~1988×3360.
        $photoX = 1138;
        $photoY = 835;
        $photoW = 702;
        $photoH = 905;

        $photoPath = PublicAssetPath::resolve($data['photo'] ?? null);
        if ($photoPath) {
            $profile = $this->prepareEmployeeIdPhoto($photoPath, $photoW, $photoH);
            $img->insert($profile, 'top-left', $photoX, $photoY);
        }

        // Vertical program label on pink stripe (centered)
        if (! empty($data['program_label'])) {
            $this->drawEmployeeProgramBar($img, (string) $data['program_label']);
        } elseif (! empty($data['program_abbr'])) {
            // Back-compat if older key is passed
            $this->drawEmployeeProgramBar($img, (string) $data['program_abbr']);
        }

        // QR code (left of photo)
        if (! empty($data['qrcode'])) {
            $qrPath = $this->makeEmployeeQrPng((string) $data['qrcode'], 420);
            $qrImage = Image::make($qrPath);
            $img->insert($qrImage, 'top-left', 540, 980);
            @unlink($qrPath);
        }

        // ID number under QR
        if (! empty($data['id_number'])) {
            $idNumber = trim((string) $data['id_number']);
            $idSize = $this->fitEmployeeIdText($idNumber, 72, 40, 480);
            $this->drawEmployeeIdText(
                $img,
                $idNumber,
                750,
                1450,
                $idSize,
                '#111111',
                'center',
                'middle',
                true
            );
        }

        // Name — large & bold inside hexagonal name bar
        $name = trim($data['full_name'] ?? '');
        if ($name !== '') {
            $display = mb_strtoupper($name);
            $nameSize = $this->fitEmployeeIdText($display, 140, 70, 1250);
            $this->drawEmployeeIdText(
                $img,
                $display,
                1188,
                2115,
                $nameSize,
                '#111111',
                'center',
                'middle',
                true
            );
        }

        return $img;
    }

    /**
     * @param  array{
     *     birth_date:?string,
     *     blood_type:?string,
     *     sex:?string,
     *     department:?string,
     *     year_start_work:?string,
     *     mobile_number:?string,
     *     address:?string,
     *     signature:?string
     * }  $data
     */
    protected function composeEmployeeIdCardBack($img, array $data)
    {
        $valueColor = '#111111';
        // Bolder / larger field values on the white rows
        $valueSize = 70;
        // Value rows on original blank back (Program / Year / Mobile / Address).
        // Certification paragraph stays baked into the template.
        $deptY = 1895;
        $yearY = 2135;
        $mobileY = 2380;
        $addressY = 2615;

        if (! empty($data['birth_date'])) {
            try {
                $dob = Carbon::parse($data['birth_date'])
                    ->timezone(config('app.timezone', 'Asia/Manila'))
                    ->format('F d, Y');
            } catch (\Throwable $e) {
                $dob = (string) $data['birth_date'];
            }
            $this->drawEmployeeIdText($img, $dob, 820, 220, $valueSize, $valueColor, 'left', 'middle', true);
        }

        if (! empty($data['blood_type'])) {
            $this->drawEmployeeIdText($img, trim((string) $data['blood_type']), 820, 448, $valueSize, $valueColor, 'left', 'middle', true);
        }

        if (! empty($data['sex'])) {
            $this->drawEmployeeIdText($img, trim((string) $data['sex']), 1860, 448, $valueSize, $valueColor, 'left', 'middle', true);
        }

        if (! empty($data['department'])) {
            $dept = trim((string) $data['department']);
            $size = $this->fitEmployeeIdText($dept, $valueSize, 36, 1450);
            $this->drawEmployeeIdText($img, $dept, 820, $deptY, $size, $valueColor, 'left', 'middle', true);
        }

        if (! empty($data['year_start_work'])) {
            $this->drawEmployeeIdText(
                $img,
                trim((string) $data['year_start_work']),
                820,
                $yearY,
                $valueSize,
                $valueColor,
                'left',
                'middle',
                true
            );
        }

        if (! empty($data['mobile_number'])) {
            $this->drawEmployeeIdText(
                $img,
                trim((string) $data['mobile_number']),
                820,
                $mobileY,
                $valueSize,
                $valueColor,
                'left',
                'middle',
                true
            );
        }

        if (! empty($data['address'])) {
            $address = trim((string) $data['address']);
            $size = $this->fitEmployeeIdText($address, $valueSize, 34, 1500);
            $this->drawEmployeeIdText($img, $address, 820, $addressY, $size, $valueColor, 'left', 'middle', true);
        }

        $signaturePath = PublicAssetPath::resolve($data['signature'] ?? null);
        if ($signaturePath) {
            $signature = Image::make($signaturePath)->resize(900, 320, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $img->insert($signature, 'top-left', 810, 2860);
        }

        return $img;
    }
}
