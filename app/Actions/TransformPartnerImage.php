<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TransformPartnerImage
{
    private const MAX_WIDTH = 1600;

    private const MAX_HEIGHT = 900;

    private const WEBP_QUALITY = 85;

    public function handle(UploadedFile $image): string
    {
        $sourceBytes = file_get_contents($image->getPathname());
        if ($sourceBytes === false) {
            throw new RuntimeException(__('partners.errors.image_read'));
        }

        $source = @imagecreatefromstring($sourceBytes);
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([
                'image' => __('partners.errors.image_invalid'),
            ]);
        }

        $oriented = $source;
        $transformed = $source;

        try {
            $oriented = $this->orient($source, $image);
            $transformed = $this->resize($oriented);
            $contents = $this->encode($transformed);
        } finally {
            if ($transformed !== $source && $transformed !== $oriented) {
                imagedestroy($transformed);
            }

            if ($oriented !== $source) {
                imagedestroy($oriented);
            }

            imagedestroy($source);
        }

        $path = 'partners/'.Str::uuid().'.webp';
        if (! Storage::put($path, $contents)) {
            throw new RuntimeException(__('partners.errors.image_store'));
        }

        return $path;
    }

    private function orient(GdImage $source, UploadedFile $image): GdImage
    {
        if ($image->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($image->getPathname(), 'IFD0', true, false);
        $orientation = is_array($exif)
            ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1)
            : 1;

        return match ($orientation) {
            2 => $this->flip($source, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($source, 180),
            4 => $this->flip($source, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($source, IMG_FLIP_HORIZONTAL), -90),
            6 => $this->rotate($source, -90),
            7 => $this->rotate($this->flip($source, IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotate($source, 90),
            default => $source,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            throw new RuntimeException(__('partners.errors.image_transform'));
        }

        return $image;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof GdImage) {
            throw new RuntimeException(__('partners.errors.image_transform'));
        }

        return $rotated;
    }

    private function resize(GdImage $source): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $sourceWidth, self::MAX_HEIGHT / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $target = imagecreatetruecolor($width, $height);

        if (! $target instanceof GdImage) {
            throw new RuntimeException(__('partners.errors.image_transform'));
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        if ($transparent === false || ! imagefill($target, 0, 0, $transparent)) {
            imagedestroy($target);

            throw new RuntimeException(__('partners.errors.image_transform'));
        }

        if (! imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        )) {
            imagedestroy($target);

            throw new RuntimeException(__('partners.errors.image_transform'));
        }

        return $target;
    }

    private function encode(GdImage $image): string
    {
        ob_start();

        try {
            $encoded = imagewebp($image, null, self::WEBP_QUALITY);
            $contents = ob_get_clean();
        } finally {
            if (ob_get_level() > 0 && ! isset($contents)) {
                ob_end_clean();
            }
        }

        if (! $encoded || ! is_string($contents)) {
            throw new RuntimeException(__('partners.errors.image_encode'));
        }

        return $contents;
    }
}
