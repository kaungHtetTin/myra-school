<?php
declare(strict_types=1);
namespace App;

use Mini\Database as DB;

final class Branding
{
    public static function save(array $settings, array $files, array $remove): void
    {
        $images = [];
        $created = [];
        try {
            // Validate both files before changing settings or writing either image.
            foreach (['logo', 'favicon'] as $kind) {
                $file = $files[$kind] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $error = $file['error'] ?? null;
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                    throw new \InvalidArgumentException(ucfirst($kind).' upload failed. Choose an image up to 2 MB.');
                }
                if (($file['size'] ?? 0) > 2 * 1024 * 1024) throw new \InvalidArgumentException(ucfirst($kind).' must be 2 MB or smaller.');
                $info = @getimagesize($file['tmp_name']);
                if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
                    || $info[0] > 4096 || $info[1] > 4096) {
                    throw new \InvalidArgumentException(ucfirst($kind).' must be a PNG, JPG, or WebP image up to 4096 × 4096 pixels.');
                }
                $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
                if (!$image) throw new \InvalidArgumentException('Unable to read the '. $kind .' image. Choose another file.');
                $images[$kind] = $image;
            }
            $directory = BASE_PATH.'/public/uploads/branding';
            if ($images && !is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create branding upload directory.');
            }
            foreach ($images as $kind => $image) {
                // Re-encode uploads as PNG; client filenames and executable payloads are never stored.
                $size = $kind === 'favicon' ? 64 : 512;
                $scale = min(1, $size / max(imagesx($image), imagesy($image)));
                $width = max(1, (int) round(imagesx($image) * $scale));
                $height = max(1, (int) round(imagesy($image) * $scale));
                $canvas = imagecreatetruecolor($kind === 'favicon' ? 64 : $width, $kind === 'favicon' ? 64 : $height);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                imagecopyresampled($canvas, $image, $kind === 'favicon' ? (int)((64-$width)/2) : 0,
                    $kind === 'favicon' ? (int)((64-$height)/2) : 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                $path = 'uploads/branding/'.$kind.'-'.bin2hex(random_bytes(16)).'.png';
                $created[] = $path;
                try {
                    if (!imagepng($canvas, BASE_PATH.'/public/'.$path)) throw new \RuntimeException('Unable to save branding image.');
                } finally { imagedestroy($canvas); }
                $settings[$kind.'_path'] = $path;
            }
            foreach (['logo', 'favicon'] as $kind) {
                if (!isset($images[$kind]) && !empty($remove[$kind])) $settings[$kind.'_path'] = null;
            }
            // Lock the settings record so concurrent replacements do not leave unused files.
            $old = DB::transaction(function () use ($settings) {
                $old = DB::fetch('SELECT * FROM settings WHERE id=1 FOR UPDATE');
                DB::update('settings', 1, $settings);
                return $old;
            });
            foreach (['logo_path', 'favicon_path'] as $column) {
                if (array_key_exists($column, $settings) && $old[$column] !== $settings[$column]) self::remove($old[$column]);
            }
        } catch (\Throwable $error) {
            foreach ($created as $path) self::remove($path);
            throw $error;
        } finally {
            foreach ($images as $image) imagedestroy($image);
        }
    }

    private static function remove(?string $path): void
    {
        if ($path && preg_match('#^uploads/branding/(logo|favicon)-[a-f0-9]{32}\.png$#D', $path)) {
            $file = BASE_PATH.'/public/'.$path;
            if (is_file($file)) @unlink($file);
        }
    }
}
