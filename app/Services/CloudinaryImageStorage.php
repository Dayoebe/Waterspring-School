<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CloudinaryImageStorage
{
    public function upload(UploadedFile $file, string $folder): string
    {
        $path = $file->getRealPath();
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        return $this->uploadFile($path, $file->getClientOriginalName(), $folder);
    }

    public function uploadFile(string $path, string $originalName, string $folder): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The local image file is unavailable.');
        }

        $mimeType = mime_content_type($path);
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('Only image files can be uploaded to Cloudinary image storage.');
        }

        $credentials = $this->credentials();
        $folder = trim($folder, '/');
        if ($folder === '') {
            throw new RuntimeException('A Cloudinary folder is required for image uploads.');
        }

        $params = [
            'api_key' => $credentials['api_key'],
            'folder' => $folder,
            'timestamp' => time(),
            'use_filename' => 1,
            'unique_filename' => 1,
        ];

        $paramsToSign = $params;
        unset($paramsToSign['api_key']);
        ksort($paramsToSign);
        $signatureBase = collect($paramsToSign)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&');
        $signature = sha1($signatureBase . $credentials['api_secret']);
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the local image file.');
        }

        $response = Http::timeout(30)
            ->attach('file', $contents, basename($originalName))
            ->post(
                'https://api.cloudinary.com/v1_1/' . $credentials['cloud_name'] . '/image/upload',
                $params + ['signature' => $signature]
            );

        if (!$response->successful()) {
            $message = data_get($response->json(), 'error.message', 'Provider returned no error message.');
            throw new RuntimeException('Cloudinary upload failed with HTTP ' . $response->status() . ': ' . $message);
        }

        $secureUrl = data_get($response->json(), 'secure_url');
        if (!is_string($secureUrl) || $secureUrl === '') {
            throw new RuntimeException('Cloudinary did not return a secure image URL.');
        }

        return $secureUrl;
    }

    public function folder(string $area, ?int $schoolId = null): string
    {
        $base = $area === 'gallery'
            ? trim((string) config('services.cloudinary.folder', 'waterspring/gallery'), '/')
            : trim((string) config('services.cloudinary.root_folder', 'waterspring'), '/') . '/' . trim($area, '/');

        if ($schoolId !== null && $schoolId > 0) {
            $base .= '/school-' . $schoolId;
        }

        return $base;
    }

    public function url(?string $path, string $disk = 'public'): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::disk($disk)->url($path);
    }

    public function isCloudinaryUrl(?string $url): bool
    {
        if (!is_string($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'res.cloudinary.com';
    }

    public function transformedUrl(string $url, string $transformation): string
    {
        if (!$this->isCloudinaryUrl($url)) {
            return $url;
        }

        $uploadSegment = '/upload/';
        $transformedSegment = $uploadSegment . trim($transformation, '/') . '/';
        if (str_contains($url, $transformedSegment)) {
            return $url;
        }

        return preg_replace(
            '#/upload/#',
            $transformedSegment,
            $url,
            1
        ) ?: $url;
    }

    public function isImageFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $mimeType = mime_content_type($path);

        return is_string($mimeType) && str_starts_with($mimeType, 'image/');
    }

    /**
     * @return array{cloud_name: string, api_key: string, api_secret: string}
     */
    protected function credentials(): array
    {
        $url = trim((string) config('services.cloudinary.url'));
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new RuntimeException('Missing or invalid CLOUDINARY_URL configuration.');
        }

        return [
            'cloud_name' => (string) $parts['host'],
            'api_key' => urldecode((string) $parts['user']),
            'api_secret' => urldecode((string) $parts['pass']),
        ];
    }
}
