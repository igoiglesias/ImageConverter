<?php
namespace Iglesias\ImageConverter;

use Exception;
use GdImage;
use Throwable;

/**
 * Utility class for converting images between formats using the GD extension.
 * Supports loading from various formats, resizing, quality adjustment, and output to base64 or disk.
 * Ensures compatibility by checking GD support for formats like JPEG, PNG, GIF, WebP, BMP, and AVIF.
 * 
 * @author [Igor Iglesias] (contato@igoriglesias.com)
 */
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

    /**
     * Converts an image to the specified format and returns it as a base64 string.
     * 
     * Loads the image from disk, applies resizing if specified, adjusts quality, and converts.
     * Useful for embedding images in HTML/CSS (e.g., <img src="data:...">).
     * 
     * @param string $file_path Absolute path to the source image file.
     * @param string $format Output format (e.g., 'webp', 'jpeg'). Default: 'webp'. Will be prefixed with 'image/'.
     * @param int $quality Quality from 0-100 (normalized by format). Default: 80. Ignored for unsupported formats (GIF, BMP).
     * @param int $width Desired width for resizing (0 to ignore). Must be used with $height > 0.
     * @param int $height Desired height for resizing (0 to ignore). Must be used with $width > 0.
     * @return string Base64 string in the format 'data:image/format;base64,...'.
     * @throws Exception If GD is not loaded, format is unsupported, file is invalid, or conversion fails.
     */
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

    /**
     * Converts an image to the specified format and saves it to disk.
     * 
     * Similar to convertToBase64, but writes the result to a file instead of returning base64.
     * Overwrites the file if it exists.
     * 
     * @param string $file_path Absolute path to the source image file.
     * @param string $save_path Absolute path to save the converted image (include extension, e.g., '/path/image.webp').
     * @param string $format Output format (e.g., 'webp', 'jpeg'). Default: 'webp'. Will be prefixed with 'image/'.
     * @param int $quality Quality from 0-100 (normalized by format). Default: 80. Ignored for unsupported formats.
     * @param int $width Desired width for resizing (0 to ignore).
     * @param int $height Desired height for resizing (0 to ignore).
     * @return void
     * @throws Exception If GD is not loaded, format is unsupported, file is invalid, or conversion/saving fails.
     */
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

    /**
     * Returns the MIME types supported by GD in the current PHP installation.
     * 
     * Queries gd_info() once and caches the result for performance.
     * Filters only types in ALLOWED_TYPES.
     * 
     * @return array List of supported MIME strings (e.g., ['image/jpeg', 'image/png']).
     */
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
        $format = 'image/' . strtolower($format);
        self::__checkGd();
        self::__checkFormat($format);
        $type = self::__checkFile($file_path);
        $quality = self::__getCalculatedQuality($quality, $format);

        // Carrega a imagem via GD
        $image = self::GD_IMAGE_LOADER[$type]($file_path);

        if ($width > 0 && $height > 0) {
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);

            // Calcula escala para cobrir a área alvo (como -resize ^)
            $scale = max($width / $origWidth, $height / $origHeight);
            $newWidth = (int) ceil($origWidth * $scale);
            $newHeight = (int) ceil($origHeight * $scale);

            // Redimensiona
            $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC_FIXED);
            imagedestroy($image); // libera a original

            // Calcula offset central para crop
            $x = (int)(($newWidth - $width) / 2);
            $y = (int)(($newHeight - $height) / 2);

            // Faz o crop central
            $cropped = imagecrop($resized, [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height
            ]);
            imagedestroy($resized); // libera a redimensionada

            if ($cropped === false) {
                throw new Exception("Failed to crop image");
            }

            $image = $cropped;
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