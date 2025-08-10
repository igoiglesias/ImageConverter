# ImageConverter PHP Library

A lightweight, efficient PHP class for converting images between formats using the GD extension. It supports dynamic detection of GD capabilities, resizing, quality adjustment, and output to disk or Base64-encoded strings. Ideal for web applications needing on-the-fly image optimization, like reducing file sizes for faster loading without external dependencies.

This library prioritizes performance by leveraging GD's native functions and avoiding unnecessary overhead. It's designed for production use but can be optimized further for high-volume scenarios.

## Requirements

- PHP 7.4+ (compatible with 8.x)
- GD extension enabled (compile with `--with-gd` or install via package manager like `apt install php-gd`)
- Memory limit: At least 128MB recommended for large images; GD can be memory-hungry during resizing.

Verify GD support: Run `php -i | grep GD` or use `ImageConverter::getTypesSupported()` in your code.

## Installation

1. Clone the repo: `git clone https://github.com/yourusername/imageconverter.git`
2. Include the class in your project: `require_once 'ImageConverter.php';`
3. Using Composer: composer `require iglesias/image-converter`


## Usage

### Convert to Disk

Save an image to a new file in the desired format, with optional quality and resize.

```php
ImageConverter::convertToDisk('input.jpg', 'output.webp', 'webp', 80, 800, 600);
```

- Parameters:
  - `$file_path`: Path to source image (string, required).
  - `$save_path`: Path to save converted image (string, required).
  - `$format`: Target format (string, default 'webp'; e.g., 'png', 'jpeg').
  - `$quality`: Compression level (int, 0-100, default 80; lower = smaller file, more lossy).
  - `$width`: New width in pixels (int, default 0 = no resize).
  - `$height`: New height in pixels (int, default 0 = no resize).

### Convert to Base64

Generate a Base64 string for inline use (e.g., in HTML `<img src>`).

```php
$base64 = ImageConverter::convertToBase64('input.jpg', 'webp', 80, 800, 600);
echo '<img src="' . $base64 . '">';
```

- Parameters: Same as `convertToDisk`, minus `$save_path`.
- Returns: `data:image/format;base64,...` string.

### Get Supported Formats

Dynamically list formats GD supports on your system.

```php
print_r(ImageConverter::getTypesSupported()); // e.g., ['image/jpeg', 'image/png', 'image/webp']
```

## Supported Formats

Depends on your GD build. Common ones: JPEG, PNG, GIF, WebP, BMP, AVIF. The class auto-detects via `gd_info()` and filters to: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/bmp`, `image/avif`.

Quality scales differently:
- JPEG/WebP/AVIF: 0-100
- PNG: 0-9 (compression level)
- GIF/BMP: No quality param (lossless)

## Error Handling

Methods throw `Exception` on issues like:
- GD not loaded
- Unsupported format/file type
- File not found/invalid
- Image creation failure

Wrap calls in try-catch for robustness:

```php
try {
    ImageConverter::convertToDisk(...);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## License

MIT License. See LICENSE file (create one if missing).