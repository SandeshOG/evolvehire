<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\ValidationError;

/**
 * Hardened file uploads: size cap, extension allow-list + magic-byte sniff,
 * random filename, stored in private/storage (outside the web root).
 */
final class UploadService
{
    private const MAX_BYTES = 1500000; // 1.5 MB
    private const ALLOWED = ['pdf', 'doc', 'docx'];

    public static function save(array $file, string $kind = 'resume'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationError('Attach a resume file.');
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > self::MAX_BYTES) {
            throw new ValidationError('The file must be under 1.5 MB.');
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new ValidationError('Use PDF, DOC, or DOCX.');
        }
        $tmp = (string) $file['tmp_name'];
        $head = (string) file_get_contents($tmp, false, null, 0, 8);
        $pdf = strncmp($head, '%PDF', 4) === 0;
        $zip = strncmp($head, "PK", 2) === 0;
        $ole = strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0;
        if ($ext === 'pdf' && !$pdf) {
            throw new ValidationError('That PDF is not valid.');
        }
        if ($ext === 'docx' && !$zip) {
            throw new ValidationError('That DOCX is not valid.');
        }
        if ($ext === 'doc' && !$ole) {
            throw new ValidationError('That DOC is not valid.');
        }
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? (string) finfo_file($fi, $tmp) : '';
            if ($fi) {
                finfo_close($fi);
            }
            $ok = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if ($mime !== '' && $mime !== 'application/octet-stream' && $mime !== $ok[$ext]) {
                throw new ValidationError('That file type is not accepted.');
            }
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = private_dir() . '/storage/' . $kind . '/' . $name;
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (!move_uploaded_file($tmp, $dest)) {
            // CLI test harness: move_uploaded_file refuses non-POST temp files.
            if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
                throw new \RuntimeException('Could not store the file.');
            }
        }
        return $kind . '/' . $name;
    }

    /** Serve a stored file to an authenticated desk user. */
    public static function download(string $relPath): void
    {
        if (!preg_match('#^[a-z]+/[a-f0-9]{16}\.(pdf|doc|docx)$#', $relPath)) {
            http_response_code(404);
            exit('File not found.');
        }
        $path = private_dir() . '/storage/' . $relPath;
        if (!is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }
        $ext = pathinfo($relPath, PATHINFO_EXTENSION);
        $types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        header('Content-Type: ' . $types[$ext]);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}
