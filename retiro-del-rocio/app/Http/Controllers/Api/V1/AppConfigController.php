<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public client bootstrap config for the tablet / mobile apps.
 *
 * No auth — returns only non-sensitive values the app needs before a device
 * is provisioned (e.g. the onboarding background video URL hosted on Cloudinary).
 */
class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'app' => [
                    'name' => config('app.name'),
                ],
                'onboarding' => [
                    'video_url' => $this->onboardingVideoUrl(),
                ],
            ],
        ]);
    }

    /**
     * Resolve the onboarding background video URL. An explicit ONBOARDING_VIDEO_URL
     * wins; otherwise it is built from the Cloudinary cloud name + public id.
     */
    private function onboardingVideoUrl(): ?string
    {
        if ($url = config('cloudinary.onboarding.video_url')) {
            return $url;
        }

        $cloud = config('cloudinary.cloud_name');
        $publicId = config('cloudinary.onboarding.video_public_id');

        if ($cloud && $publicId) {
            return "https://res.cloudinary.com/{$cloud}/video/upload/{$publicId}.mp4";
        }

        return null;
    }
}
