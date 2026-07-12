<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\HotelSettings;
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
                // Slow-moving values (Settings → Hotel Info). Fetched once at
                // launch and cached, so the 20-second room-status poll stays lean.
                'hotel' => [
                    'name' => HotelSettings::get('hotel.name'),
                    'tagline' => HotelSettings::get('hotel.tagline'),
                    'address' => HotelSettings::get('hotel.address'),
                    'city' => HotelSettings::get('hotel.city'),
                    'country' => HotelSettings::get('hotel.country'),
                    'phone' => HotelSettings::get('hotel.phone'),
                    'email' => HotelSettings::get('hotel.email'),
                    'description' => HotelSettings::get('hotel.description'),
                    'check_in_time' => HotelSettings::checkInTime(),
                    'check_out_time' => HotelSettings::checkOutTime(),
                    'check_in_label' => HotelSettings::checkInLabel(),
                    'check_out_label' => HotelSettings::checkOutLabel(),
                ],
                // Realtime: the tablet subscribes here to be told the instant a
                // guest is checked in or out, instead of waiting for its poll.
                'broadcasting' => [
                    'driver' => config('broadcasting.default'),
                    'key' => config('broadcasting.connections.reverb.key'),
                    'host' => config('broadcasting.connections.reverb.options.host'),
                    'port' => (int) config('broadcasting.connections.reverb.options.port'),
                    'scheme' => config('broadcasting.connections.reverb.options.scheme'),
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
