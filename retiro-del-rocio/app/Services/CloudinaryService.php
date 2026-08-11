<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Uploads files to Cloudinary using its signed REST API.
 *
 * Deliberately no SDK dependency: a signed multipart POST is all Cloudinary
 * needs, and it keeps the credentials server-side (the tablet never talks to
 * Cloudinary directly). Every upload returns the `public_id` — the stable id the
 * booking stores — alongside the delivery URL the admin page renders.
 *
 * Never throws to the caller: if Cloudinary is not configured, or the upload
 * fails, it returns null so a guest can still be checked in on the strength of
 * their document number while the scan is retried or added later.
 */
class CloudinaryService
{
    public function isConfigured(): bool
    {
        return filled(config('cloudinary.cloud_name'))
            && filled(config('cloudinary.api_key'))
            && filled(config('cloudinary.api_secret'));
    }

    /**
     * Upload a file and return ['public_id' => ..., 'url' => ...], or null.
     *
     * @param  string  $folder  Cloudinary folder the asset lands in.
     */
    public function upload(UploadedFile $file, string $folder = 'checkin-ids'): ?array
    {
        if (! $this->isConfigured()) {
            Log::info('CloudinaryService: not configured — skipping upload.');

            return null;
        }

        $cloud = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');
        $timestamp = time();

        // Cloudinary signs the sorted, ampersand-joined params (excluding file,
        // api_key and resource_type) followed by the api secret.
        $signature = sha1("folder={$folder}&timestamp={$timestamp}".$apiSecret);

        try {
            // `auto` lets Cloudinary store images and PDFs alike.
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'document')
                ->post("https://api.cloudinary.com/v1_1/{$cloud}/auto/upload", [
                    'api_key' => $apiKey,
                    'timestamp' => $timestamp,
                    'folder' => $folder,
                    'signature' => $signature,
                ]);

            if ($response->failed()) {
                Log::warning('CloudinaryService: upload failed — '.$response->status().' '.$response->body());

                return null;
            }

            $publicId = $response->json('public_id');
            $url = $response->json('secure_url');
            if (! $publicId || ! $url) {
                Log::warning('CloudinaryService: upload response missing public_id/secure_url.');

                return null;
            }

            return ['public_id' => (string) $publicId, 'url' => (string) $url];
        } catch (Throwable $e) {
            Log::warning('CloudinaryService: upload threw — '.$e->getMessage());

            return null;
        }
    }
}
