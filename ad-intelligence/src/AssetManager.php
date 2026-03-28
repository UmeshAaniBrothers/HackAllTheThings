<?php

/**
 * AssetManager - Media File Download & Storage
 *
 * Downloads ad creative assets (images, videos) and stores them locally.
 * Maintains mapping between original URLs and local file paths.
 */
class AssetManager
{
    private string $mediaPath;
    private string $driver;

    public function __construct(array $storageConfig)
    {
        $this->mediaPath = rtrim($storageConfig['media_path'], '/') . '/';
        $this->driver = $storageConfig['driver'] ?? 'local';

        if (!is_dir($this->mediaPath)) {
            mkdir($this->mediaPath, 0755, true);
        }
    }

    /**
     * Download an asset from URL and store locally.
     * Returns the local file path relative to media directory.
     */
    public function downloadAsset(string $url, string $creativeId, string $type = 'image'): ?string
    {
        if (empty($url)) {
            return null;
        }

        $extension = $this->getExtensionFromUrl($url, $type);
        $filename = $creativeId . '_' . md5($url) . '.' . $extension;
        $subDir = $this->getSubDirectory($type);
        $fullDir = $this->mediaPath . $subDir;

        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $localPath = $subDir . '/' . $filename;
        $fullPath = $this->mediaPath . $localPath;

        // Skip if already downloaded
        if (file_exists($fullPath)) {
            return $localPath;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false || $httpCode !== 200) {
            return null;
        }

        if (file_put_contents($fullPath, $content) === false) {
            return null;
        }

        return $localPath;
    }

    /**
     * Save a base64-encoded asset to disk.
     */
    public function saveBase64Asset(string $base64Data, string $creativeId, string $type = 'image'): ?string
    {
        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            return null;
        }

        $extension = $this->detectExtensionFromBinary($decoded, $type);
        $filename = $creativeId . '_' . md5($base64Data) . '.' . $extension;
        $subDir = $this->getSubDirectory($type);
        $fullDir = $this->mediaPath . $subDir;

        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $localPath = $subDir . '/' . $filename;
        $fullPath = $this->mediaPath . $localPath;

        if (file_put_contents($fullPath, $decoded) === false) {
            return null;
        }

        return $localPath;
    }

    /**
     * Get the full filesystem path for a local path.
     */
    public function getFullPath(string $localPath): string
    {
        return $this->mediaPath . $localPath;
    }

    /**
     * Get subdirectory based on asset type.
     */
    private function getSubDirectory(string $type): string
    {
        return match ($type) {
            'video'     => 'videos',
            'image'     => 'images',
            'thumbnail' => 'thumbnails',
            default     => 'other',
        };
    }

    /**
     * Extract file extension from URL.
     */
    private function getExtensionFromUrl(string $url, string $type): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'avi'])) {
                return $ext;
            }
        }

        return match ($type) {
            'video' => 'mp4',
            default => 'jpg',
        };
    }

    /**
     * Detect file extension from binary content magic bytes.
     */
    private function detectExtensionFromBinary(string $data, string $type): string
    {
        $header = substr($data, 0, 8);

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($header, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($header, "GIF8")) {
            return 'gif';
        }
        if (str_starts_with($header, "RIFF") && substr($data, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return match ($type) {
            'video' => 'mp4',
            default => 'jpg',
        };
    }
}
