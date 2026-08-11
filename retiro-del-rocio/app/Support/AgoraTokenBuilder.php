<?php

namespace App\Support;

use BoogieFromZk\AgoraToken\RtcTokenBuilder2;

/**
 * Signs the short-lived RTC token a tablet needs to join one Intercom call's
 * Agora channel. Wraps {@see RtcTokenBuilder2} so every caller uses the same
 * publisher role and TTL instead of re-deriving Agora's token args by hand.
 */
class AgoraTokenBuilder
{
    /** The RTC token for [$uid] to publish and subscribe audio in [$channel]. */
    public static function forUid(string $channel, int $uid): ?string
    {
        $appId = config('services.agora.app_id');
        $appCertificate = config('services.agora.app_certificate');

        if (! $appId || ! $appCertificate) {
            return null;
        }

        return RtcTokenBuilder2::buildTokenWithUid(
            $appId,
            $appCertificate,
            $channel,
            $uid,
            RtcTokenBuilder2::ROLE_PUBLISHER,
            (int) config('services.agora.token_ttl_seconds', 14400),
        );
    }
}
