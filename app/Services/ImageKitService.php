<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageKitService
{
    protected string $publicKey;
    protected string $privateKey;
    protected string $urlEndpoint;

    public function __construct()
    {
        $this->publicKey = config('services.imagekit.public_key', env('IMAGEKIT_PUBLIC_KEY', 'public_t65SBzKhS7mvo2WlfsobLFKsH7w='));
        $this->privateKey = config('services.imagekit.private_key', env('IMAGEKIT_PRIVATE_KEY', 'private_lumI6KJa9uDbXdM9lGqQkSb0z5I='));
        $this->urlEndpoint = config('services.imagekit.url_endpoint', env('IMAGEKIT_URL_ENDPOINT', 'https://ik.imagekit.io/muuqkim6d'));
    }

    /**
     * Upload a file (UploadedFile, file path, base64 or URL) to ImageKit Cloud.
     *
     * @param UploadedFile|string $file
     * @param string $folder e.g. '/cricapp/recordings', '/cricapp/avatars', '/cricapp/grounds'
     * @param string|null $fileName
     * @return string|null The permanent ImageKit CDN URL or null on failure
     */
    public function upload($file, string $folder = '/cricapp', ?string $fileName = null): ?string
    {
        if (empty($this->privateKey)) {
            Log::warning('ImageKit private key is missing.');
            return null;
        }

        try {
            $authHeader = 'Basic ' . base64_encode($this->privateKey . ':');
            $request = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $authHeader,
            ]);

            if ($file instanceof UploadedFile) {
                $actualName = $fileName ?: ($file->getClientOriginalName() ?: 'file_' . time());
                $response = $request->attach(
                    'file',
                    fopen($file->getRealPath(), 'r'),
                    $actualName
                )->post('https://upload.imagekit.io/api/v1/files/upload', [
                    'fileName' => $actualName,
                    'folder' => $folder,
                    'useUniqueFileName' => 'true',
                ]);
            } elseif (is_string($file) && file_exists($file)) {
                $actualName = $fileName ?: basename($file);
                $response = $request->attach(
                    'file',
                    fopen($file, 'r'),
                    $actualName
                )->post('https://upload.imagekit.io/api/v1/files/upload', [
                    'fileName' => $actualName,
                    'folder' => $folder,
                    'useUniqueFileName' => 'true',
                ]);
            } else {
                // Remote URL or Base64 data string
                $actualName = $fileName ?: 'file_' . time();
                $response = $request->asMultipart()->post('https://upload.imagekit.io/api/v1/files/upload', [
                    [
                        'name' => 'file',
                        'contents' => (string) $file,
                    ],
                    [
                        'name' => 'fileName',
                        'contents' => $actualName,
                    ],
                    [
                        'name' => 'folder',
                        'contents' => $folder,
                    ],
                    [
                        'name' => 'useUniqueFileName',
                        'contents' => 'true',
                    ],
                ]);
            }

            if ($response->successful()) {
                $json = $response->json();
                $url = $json['url'] ?? null;
                if ($url) {
                    return $url;
                }
            }

            Log::error('ImageKit upload error: ' . $response->status() . ' - ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('ImageKit upload exception: ' . $e->getMessage());
        }

        return null;
    }
}
