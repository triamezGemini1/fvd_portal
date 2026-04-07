<?php

declare(strict_types=1);

/**
 * Reduce peso de imágenes subidas conservando buena calidad (JPEG/WebP ~91, PNG sin pérdida).
 * Usa el tipo IMAGETYPE_* de getimagesize (el índice 'mime' a veces viene vacío en Windows/WAMP).
 */
final class ImageUploadCompressor
{
    /** Si supera este tamaño, se intenta optimizar (bytes). */
    private const MIN_SIZE_BYTES = 45_000;

    /** Redimensionar si el lado mayor supera esto (px). */
    private const MAX_EDGE_PX = 2048;

    /** Calidad JPEG al guardar (1–100). */
    private const JPEG_QUALITY = 91;

    /** Calidad WebP (0–100). */
    private const WEBP_QUALITY = 91;

    /** Compresión PNG (0–9), sin pérdida. */
    private const PNG_COMPRESSION = 6;

    public static function optimizeIfLarge(string $absolutePath): void
    {
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return;
        }
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $size = @filesize($absolutePath);
        if ($size === false || $size < 1) {
            return;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false || !isset($info[0], $info[1], $info[2])) {
            return;
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        $type = (int) $info[2];

        if (!self::isSupportedType($type)) {
            return;
        }

        $needsScale = $w > self::MAX_EDGE_PX || $h > self::MAX_EDGE_PX;
        $needsPass = $size > self::MIN_SIZE_BYTES || $needsScale;
        if (!$needsPass) {
            return;
        }

        $src = self::createFromFile($absolutePath, $type);
        if ($src === false) {
            return;
        }

        if ($needsScale) {
            $ratio = min(self::MAX_EDGE_PX / $w, self::MAX_EDGE_PX / $h, 1.0);
            $nw = max(1, (int) round($w * $ratio));
            $nh = max(1, (int) round($h * $ratio));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst === false) {
                imagedestroy($src);

                return;
            }
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
            imagealphablending($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
        } else {
            $dst = $src;
        }

        $tmp = $absolutePath . '.fvdopt.' . bin2hex(random_bytes(4));
        if (!self::writeToFile($dst, $tmp, $type)) {
            imagedestroy($dst);
            @unlink($tmp);

            return;
        }
        imagedestroy($dst);

        if (!is_file($tmp)) {
            return;
        }
        $newSize = @filesize($tmp);
        if ($newSize === false || $newSize < 1) {
            @unlink($tmp);

            return;
        }

        // Si solo re-codificamos y el archivo creció mucho, mantener el original.
        if (!$needsScale && $newSize > $size * 1.08) {
            @unlink($tmp);

            return;
        }

        if (!@unlink($absolutePath) && is_file($absolutePath)) {
            // En Windows a veces unlink falla si el archivo está bloqueado; intentar sobrescribir.
            if (!@copy($tmp, $absolutePath)) {
                @unlink($tmp);

                return;
            }
            @unlink($tmp);

            return;
        }

        if (!@rename($tmp, $absolutePath)) {
            if (@copy($tmp, $absolutePath)) {
                @unlink($tmp);
            } else {
                @unlink($tmp);
            }
        }
    }

    private static function isSupportedType(int $type): bool
    {
        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_PNG) {
            return true;
        }
        if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) {
            return function_exists('imagecreatefromwebp') && function_exists('imagewebp');
        }

        return false;
    }

    /** @return resource|\GdImage|false */
    private static function createFromFile(string $path, int $type)
    {
        if ($type === IMAGETYPE_JPEG) {
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
        }
        if ($type === IMAGETYPE_PNG) {
            return @imagecreatefrompng($path);
        }
        if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) {
            return @imagecreatefromwebp($path);
        }

        return false;
    }

    /**
     * @param resource|\GdImage $image
     */
    private static function writeToFile($image, string $path, int $type): bool
    {
        if ($type === IMAGETYPE_JPEG) {
            return @imagejpeg($image, $path, self::JPEG_QUALITY);
        }
        if ($type === IMAGETYPE_PNG) {
            imagesavealpha($image, true);

            return @imagepng($image, $path, self::PNG_COMPRESSION);
        }
        if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) {
            return @imagewebp($image, $path, self::WEBP_QUALITY);
        }

        return false;
    }
}
