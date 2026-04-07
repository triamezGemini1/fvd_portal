<?php
/**
 * Carga, validación y resolución de URLs para imágenes (avatares, fotos, etc.).
 */

declare(strict_types=1);

class MediaService
{
    /** @var list<string> */
    private array $allowedMime = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** @var list<string> */
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private int $maxBytes;

    private string $uploadBaseDir;

    private string $publicBaseUrl;

    private string $defaultAvatarDataUri;

    public function __construct(
        string $uploadBaseDir,
        string $publicBaseUrl = '',
        ?int $maxBytes = null,
        ?string $defaultAvatarDataUri = null
    ) {
        $this->uploadBaseDir = rtrim($uploadBaseDir, DIRECTORY_SEPARATOR . '/');
        $this->publicBaseUrl = rtrim($publicBaseUrl, '/');
        $this->maxBytes = $maxBytes ?? (5 * 1024 * 1024);
        $this->defaultAvatarDataUri = $defaultAvatarDataUri ?? self::builtinDefaultAvatar();
    }

    /**
     * Avatar por defecto (SVG embebido) si no hay archivo o la ruta no es válida.
     */
    public static function builtinDefaultAvatar(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
            . '<rect width="96" height="96" fill="#e2e8f0"/>'
            . '<circle cx="48" cy="36" r="16" fill="#94a3b8"/>'
            . '<path fill="#94a3b8" d="M24 78c4-18 44-18 48 0v6H24z"/>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    public function setMaxBytes(int $bytes): void
    {
        $this->maxBytes = $bytes;
    }

    /**
     * @param array<string, mixed> $file Elemento de $_FILES['campo']
     * @return array{ok:bool, error?:string}
     */
    public function validateUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No se seleccionó ningún archivo.'];
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo (código ' . $err . ').'];
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Archivo temporal no válido.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxBytes) {
            return ['ok' => false, 'error' => 'El archivo supera el tamaño máximo permitido.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if ($mime === false || !in_array($mime, $this->allowedMime, true)) {
            return ['ok' => false, 'error' => 'Tipo de imagen no permitido.'];
        }

        $orig = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $this->allowedExtensions, true)) {
            return ['ok' => false, 'error' => 'Extensión no permitida.'];
        }

        return ['ok' => true];
    }

    /**
     * Guarda un upload validado bajo un subdirectorio del base de uploads.
     *
     * @param array<string, mixed> $file
     * @return string|false Ruta relativa (p. ej. avatars/abc.jpg) o false
     */
    public function storeUploaded(array $file, string $subdirectory = '')
    {
        $check = $this->validateUpload($file);
        if (!$check['ok']) {
            return false;
        }

        $sub = trim(str_replace(['..', '\\'], ['', '/'], $subdirectory), '/');
        $destDir = $this->uploadBaseDir . ($sub !== '' ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub) : '');
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return false;
        }

        $orig = (string) ($file['name'] ?? 'image');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $basename = bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $destDir . DIRECTORY_SEPARATOR . $basename;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return false;
        }

        $rel = ($sub !== '' ? $sub . '/' : '') . $basename;

        return str_replace('\\', '/', $rel);
    }

    /**
     * URL pública para una ruta relativa ya almacenada.
     */
    public function urlForRelative(?string $relativePath): string
    {
        if ($relativePath === null || $relativePath === '') {
            return $this->defaultAvatar();
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $full = $this->uploadBaseDir . '/' . ltrim($relativePath, '/');
        if (!is_file($full)) {
            return $this->defaultAvatar();
        }

        if ($this->publicBaseUrl !== '') {
            return $this->publicBaseUrl . '/' . ltrim($relativePath, '/');
        }

        return '/' . ltrim($relativePath, '/');
    }

    /**
     * Igual que urlForRelative pero expuesto como nombre pedido (avatar por defecto incluido).
     */
    public function resolveDisplayUrl(?string $storedPath): string
    {
        return $this->urlForRelative($storedPath);
    }

    public function defaultAvatar(): string
    {
        return $this->defaultAvatarDataUri;
    }

    /**
     * Etiqueta img HTML escapada (src puede ser data: o http(s)).
     *
     * @param array<string, string> $extraAttrs p. ej. ['class' => 'rounded-circle']
     */
    public function imgTag(?string $storedPath, string $alt = '', array $extraAttrs = []): string
    {
        $src = htmlspecialchars($this->resolveDisplayUrl($storedPath), ENT_QUOTES, 'UTF-8');
        $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $attrs = ' src="' . $src . '" alt="' . $altEsc . '"';
        foreach ($extraAttrs as $k => $v) {
            $attrs .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<img' . $attrs . '>';
    }

    public function fileExists(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        $full = $this->uploadBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));

        return is_file($full);
    }
}
