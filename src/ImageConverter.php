<?php
namespace Iglesias\ImageConverter;

use Exception;
use GdImage;
use Throwable;

class ImageConverter {

    private const array ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/avif'];
    private const array GD_IMAGE_LOADER = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/gif' => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
        'image/bmp' => 'imagecreatefrombmp',
        'image/avif' => 'imagecreatefromavif'
    ];
    private const array GD_IMAGE_TYPE = [
        'image/jpeg' => 'imagejpeg',
        'image/png' => 'imagepng',
        'image/gif' => 'imagegif',
        'image/webp' => 'imagewebp',
        'image/bmp' => 'imagebmp',
        'image/avif' => 'imageavif'
    ];
    private const array GD_IMAGE_MAX_QUALITY = [
        'image/jpeg' => 100,
        'image/webp' => 100,
        'image/avif' => 100,
        'image/png' => 9,
        'image/gif' => null,
        'image/bmp' => null,
    ];
    private static ?array $types_supported = null;

    public static function convertToBase64(
        string $file_path,
        string $format='webp',
        int $quality=80,
        int $width=0,
        int $height=0): string {
        [$image, $quality, $format] = self::__loadAndPrepareImage($file_path, $format, $quality, $width, $height);

        ob_start();
        try {
            self::__convert($image, null, $format, $quality);
            $binary = (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            imagedestroy($image);
            throw $e;
        }

        imagedestroy($image);
        $base64 = base64_encode($binary);

        return 'data:'.$format.';base64,'.$base64;
    }

    public static function convertToDisk(
        string $file_path,
        string $save_path,
        string $format='webp',
        int $quality=80,
        int $width=0,
        int $height=0): void {
        
        [$image, $quality, $format] = self::__loadAndPrepareImage($file_path, $format, $quality, $width, $height);

        try {
            self::__convert($image, $save_path, $format, $quality);
        } finally {
            imagedestroy($image);
        }
    }

    public static function getTypesSupported(): array {
        if (self::$types_supported !== null) {
            return self::$types_supported;
        }

        self::$types_supported = [];
        foreach (gd_info() as $key => $value) {
            if ($value == 1) {
                $key_lower = strtolower($key);
                $type = str_replace('support', '', $key_lower);
                $type = 'image/'.explode(' ', $type)[0];
                if (!in_array($type, self::ALLOWED_TYPES) || in_array($type, self::$types_supported)) {
                    continue;
                }
                self::$types_supported[] = $type;
            }
        }

        return self::$types_supported;
    }

    private static function __convert(
        GdImage &$image,
        ?string $save_path,
        string $format,
        ?int $quality): void {
        if($quality !== null) {
            self::GD_IMAGE_TYPE[$format]($image, $save_path, $quality);
        }else {
            self::GD_IMAGE_TYPE[$format]($image, $save_path);
        }
    }

    private static function __getCalculatedQuality(int $quality, string $format): ?int {
        if($quality <0) $quality = 0;
        if($quality > 100) $quality = 100;

        if(self::GD_IMAGE_MAX_QUALITY[$format] !== null) {
            return (int) round(($quality/100) * self::GD_IMAGE_MAX_QUALITY[$format]);
        }

        return null;
    }

    private static function __loadAndPrepareImage(
        string $file_path,
        string $format,
        int $quality,
        int $width,
        int $height): array {
        $format = 'image/'.strtolower($format);
        self::__checkGd();
        self::__checkFormat($format);
        $type = self::__checkFile($file_path);
        $quality = self::__getCalculatedQuality($quality, $format);

        $image = self::GD_IMAGE_LOADER[$type]($file_path);

        if ($width > 0 && $height > 0) {
            $image = imagescale($image, $width, $height, IMG_BICUBIC_FIXED);
        }

        if (!$image) {
            throw new Exception("Failed to create image");
        }

        return [$image, $quality, $format];
    }

    private static function __checkFormat(string $format): void {
        if (!in_array($format, self::getTypesSupported())) {
            throw new Exception('Format not supported');
        }
    }

    private static function __checkFile(string $file): string {
        if (!file_exists($file)) {
            throw new Exception('File does not exist');
        }

        $info = getimagesize($file);
        if ($info === false) {
            throw new Exception('Not a valid image file');
        }

        $type = $info['mime'];
        if (!in_array($type, self::getTypesSupported())) {
            throw new Exception('File type is not supported');
        }

        return $type;
    }

    private static function __checkGd(): void {
        if (!extension_loaded('gd')) {
            throw new Exception('GD extension is not loaded');
        }
    }
}