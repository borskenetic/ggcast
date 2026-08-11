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
        $path = PublicAssetPath::resolve("images/id_templates/{$name}.png")
            ?? base_path("images/id_templates/{$name}.png");

        return Image::make($path);
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
     * Abbreviate long program/department names for the vertical pink bar
     * (e.g. "Bachelor of Science in Computer Science" → "BSCS", "BSEd English" → "BSED ENG").
     */
    protected function abbreviateEmployeeProgram(?string $program, ?string $department = null, ?string $position = null): string
    {
        foreach ([$program, $department, $position] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            // Already short / acronym-like
            if (mb_strlen($candidate) <= 12 && preg_match('/^[A-Za-z0-9.\-\s]+$/', $candidate)) {
                // Still expand if it is a long phrase without spaces? keep as-is.
                if (! preg_match('/\b(bachelor|master|science|education|arts|technology|department|office)\b/i', $candidate)) {
                    return mb_strtoupper(preg_replace('/\s+/', ' ', $candidate));
                }
            }

            $stop = ['of', 'in', 'and', 'the', 'for', 'to', 'a', 'an', 'at', 'on'];
            $words = preg_split('/\s+/', $candidate) ?: [];
            $abbr = '';
            $kept = [];
            foreach ($words as $word) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';
                if ($clean === '' || in_array(strtolower($clean), $stop, true)) {
                    continue;
                }
                $kept[] = $clean;
                $abbr .= mb_strtoupper(mb_substr($clean, 0, 1));
            }

            // Prefer richer short form when only a few real words (e.g. BSEd + ENG)
            if (count($kept) >= 2 && count($kept) <= 4 && mb_strlen($abbr) <= 4) {
                // "Bachelor Secondary Education English" style: use first letter group + last word if short
                $last = $kept[count($kept) - 1];
                if (mb_strlen($last) <= 4 && $abbr !== mb_strtoupper($last)) {
                    $core = mb_substr($abbr, 0, max(1, mb_strlen($abbr) - 1));

                    return trim($core.' '.mb_strtoupper($last));
                }
            }

            if ($abbr !== '') {
                return $abbr;
            }
        }

        return '';
    }

    /**
     * Draw vertical white program text on the pink left stripe (90° via GD, no Imagick).
     * Text reads bottom → top, matching the reference student/personnel cards.
     */
    protected function drawEmployeeProgramBar($img, string $abbr): void
    {
        $abbr = trim($abbr);
        if ($abbr === '') {
            return;
        }

        $fontPath = $this->employeeFontPath(true);
        if (! is_file($fontPath)) {
            return;
        }

        $size = $this->fitEmployeeIdText($abbr, 120, 64, 1100);
        $bbox = imagettfbbox($size, 0, $fontPath, $abbr);
        if ($bbox === false) {
            return;
        }

        $textW = abs($bbox[2] - $bbox[0]) + 28;
        $textH = abs($bbox[7] - $bbox[1]) + 28;
        $src = imagecreatetruecolor(max(1, $textW), max(1, $textH));
        imagesavealpha($src, true);
        $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
        imagefill($src, 0, 0, $transparent);
        $white = imagecolorallocate($src, 255, 255, 255);

        $baseX = 14 - (int) $bbox[0];
        $baseY = 14 - (int) $bbox[7];
        foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [-1, -1], [1, 1], [0, 0]] as [$ox, $oy]) {
            imagettftext($src, $size, 0, $baseX + $ox, $baseY + $oy, $white, $fontPath, $abbr);
        }

        $rotated = imagerotate($src, 90, $transparent);
        imagesavealpha($rotated, true);
        imagedestroy($src);

        $rotW = imagesx($rotated);
        $rotH = imagesy($rotated);
        // Pink bar ~x 0–455, center ~228; vertical mid of content ~1400
        $x = (int) (228 - ($rotW / 2));
        $y = (int) (1400 - ($rotH / 2));

        $tmpPath = storage_path('app/emp_program_bar_'.uniqid('', true).'.png');
        imagepng($rotated, $tmpPath);
        imagedestroy($rotated);

        $bar = Image::make($tmpPath);
        $img->insert($bar, 'top-left', max(0, $x), max(0, $y));
        @unlink($tmpPath);
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
     * @param  array{
     *     photo:?string,
     *     full_name:string,
     *     program_abbr:?string,
     *     qrcode:?string,
     *     id_number:?string
     * }  $data
     */
    protected function composeEmployeeIdCardFront($img, array $data)
    {
        // Photo frame interior — fill edge-to-edge inside the green border.
        $photoX = 1140;
        $photoY = 830;
        $photoSize = 700;

        $photoPath = PublicAssetPath::resolve($data['photo'] ?? null);
        if ($photoPath) {
            $profile = Image::make($photoPath)->fit($photoSize, $photoSize, function ($constraint) {
                $constraint->upsize();
            });
            $img->insert($profile, 'top-left', $photoX, $photoY);
        }

        // Vertical program/department abbreviation on pink stripe
        if (! empty($data['program_abbr'])) {
            $this->drawEmployeeProgramBar($img, (string) $data['program_abbr']);
        }

        // QR code (left of photo, similar to student-style reference)
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
        $valueSize = 58;
        // Template y positions for Department / Year / Mobile / Address rows after cert was removed.
        // These stay on original band Ys of the baked template (labels already on image).
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
