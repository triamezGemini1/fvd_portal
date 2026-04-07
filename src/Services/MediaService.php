<?php

declare(strict_types=1);

namespace FvdPortal\Services;

/**
 * Optimización de imágenes para el portal (WebP, redimensión).
 */
final class MediaService
{
    private const MAX_WIDTH_PX = 800;

    private const WEBP_QUALITY = 80;

    /**
     * Redimensiona (ancho máximo 800px, proporción conservada) y guarda como WebP (calidad 80).
     *
     * @param string $file      Ruta absoluta o relativa al archivo fuente (subida temporal o existente)
     * @param string $targetPath Ruta del archivo .webp de salida
     *
     * @return bool true si se generó el archivo de destino
     */
    public static function optimizeImage(string $file, string $targetPath): bool
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
        $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        $info = @getimagesize($file);
        if ($info === false || !isset($info[0], $info[1], $info[2])) {
            return false;
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        $type = (int) $info[2];

        $src = self::createFromFile($file, $type);
        if ($src === false) {
            return false;
        }

        if ($w > self::MAX_WIDTH_PX) {
            $ratio = self::MAX_WIDTH_PX / $w;
            $nw = self::MAX_WIDTH_PX;
            $nh = max(1, (int) round($h * $ratio));
        } else {
            $nw = $w;
            $nh = $h;
        }

        if ($nw !== $w || $nh !== $h) {
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst === false) {
                imagedestroy($src);

                return false;
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

        $dir = dirname($targetPath);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                imagedestroy($dst);

                return false;
            }
        }

        $ok = imagewebp($dst, $targetPath, self::WEBP_QUALITY);
        imagedestroy($dst);

        return $ok && is_file($targetPath);
    }

    /** @return resource|\GdImage|false */
    private static function createFromFile(string $path, int $type)
    {
        if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
            return @imagecreatefromjpeg($path);
        }
        if ($type === IMAGETYPE_PNG) {
            return @imagecreatefrompng($path);
        }
        if ($type === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
            return @imagecreatefromgif($path);
        }
        if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            return @imagecreatefromwebp($path);
        }

        return false;
    }
}
