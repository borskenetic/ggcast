<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PublicAssetPath;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;

/**
 * Faculty/staff Library ID — distinct templates from student cards.
 * Coordinates target public/images/id_templates/{front|back}_employee.png
 * at ~1988×3360 (front) and ~2520×3360 (back).
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

    protected function drawEmployeeIdText(
        $img,
        string $text,
        int $x,
        int $y,
        int $size,
        string $color = '#111111',
        string $align = 'left',
        string $valign = 'middle'
    ): void {
        $fontPath = public_path('fonts/arial.ttf');
        if (! is_file($fontPath) || $text === '') {
            return;
        }

        $img->text($text, $x, $y, function ($font) use ($fontPath, $size, $color, $align, $valign) {
            $font->file($fontPath);
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign($valign);
        });
    }

    /**
     * Shrink text until it fits $maxWidth (approx; Arial ≈ 0.55×size per char).
     */
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
     * @param  array{photo:?string,full_name:string}  $data
     */
    protected function composeEmployeeIdCardFront($img, array $data)
    {
        // Photo frame interior (rounded square on upper-right content area)
        $photoX = 1125;
        $photoY = 860;
        $photoSize = 720;

        $photoPath = PublicAssetPath::resolve($data['photo'] ?? null);
        if ($photoPath) {
            $profile = Image::make($photoPath)->fit($photoSize, $photoSize);
            $img->insert($profile, 'top-left', $photoX, $photoY);
        }

        $name = trim($data['full_name'] ?? '');
        if ($name !== '') {
            $nameSize = $this->fitEmployeeIdText(mb_strtoupper($name), 90, 48, 1200);
            // Center of white hexagonal name bar
            $this->drawEmployeeIdText(
                $img,
                mb_strtoupper($name),
                1188,
                2115,
                $nameSize,
                '#111111',
                'center',
                'middle'
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

        // Date of Birth value (right of green label)
        if (! empty($data['birth_date'])) {
            try {
                $dob = Carbon::parse($data['birth_date'])
                    ->timezone(config('app.timezone', 'Asia/Manila'))
                    ->format('F d, Y');
            } catch (\Throwable $e) {
                $dob = (string) $data['birth_date'];
            }
            $this->drawEmployeeIdText($img, $dob, 820, 220, $valueSize, $valueColor, 'left', 'middle');
        }

        // Blood Type
        if (! empty($data['blood_type'])) {
            $this->drawEmployeeIdText($img, trim((string) $data['blood_type']), 820, 448, $valueSize, $valueColor, 'left', 'middle');
        }

        // Sex
        if (! empty($data['sex'])) {
            $this->drawEmployeeIdText($img, trim((string) $data['sex']), 1860, 448, $valueSize, $valueColor, 'left', 'middle');
        }

        // Department (label is "Department:" on employee template)
        if (! empty($data['department'])) {
            $dept = trim((string) $data['department']);
            $size = $this->fitEmployeeIdText($dept, $valueSize, 36, 1450);
            $this->drawEmployeeIdText($img, $dept, 820, 1895, $size, $valueColor, 'left', 'middle');
        }

        // Year of start of work in this HEI
        if (! empty($data['year_start_work'])) {
            $this->drawEmployeeIdText(
                $img,
                trim((string) $data['year_start_work']),
                820,
                2135,
                $valueSize,
                $valueColor,
                'left',
                'middle'
            );
        }

        // Mobile Number
        if (! empty($data['mobile_number'])) {
            $this->drawEmployeeIdText(
                $img,
                trim((string) $data['mobile_number']),
                820,
                2380,
                $valueSize,
                $valueColor,
                'left',
                'middle'
            );
        }

        // Address (may wrap / shrink)
        if (! empty($data['address'])) {
            $address = trim((string) $data['address']);
            $size = $this->fitEmployeeIdText($address, $valueSize, 34, 1500);
            $this->drawEmployeeIdText($img, $address, 820, 2615, $size, $valueColor, 'left', 'middle');
        }

        // Employee signature above "Employee's Signature" caption
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
