<?php

namespace App\Services;

/**
 * Agora Token Builder Service (AccessToken2 / Version 007).
 * Pure PHP implementation with HMAC-SHA256, pack, and zlib_encode.
 */
class AgoraTokenService
{
    const ROLE_PUBLISHER = 1;
    const ROLE_SUBSCRIBER = 2;

    /**
     * Build an Agora RTC AccessToken2.
     *
     * @param string $channelName
     * @param int|string $uid
     * @param int $role
     * @param int $expireSeconds
     * @return string|null
     */
    public function buildToken(string $channelName, int|string $uid, int $role = self::ROLE_SUBSCRIBER, int $expireSeconds = 7200): ?string
    {
        $appId = config('services.agora.app_id', env('AGORA_APP_ID', ''));
        $appCertificate = config('services.agora.app_certificate', env('AGORA_APP_CERTIFICATE', ''));

        if (empty($appId) || empty($appCertificate)) {
            return null;
        }

        try {
            $issueTs = time();
            $expire = $expireSeconds;
            $salt = rand(1, 99999999);

            $serviceRtcType = 1;
            $privilegeJoinChannel = 1;
            $privilegePublishAudio = 2;
            $privilegePublishVideo = 3;
            $privilegePublishData = 4;

            $privileges = [
                $privilegeJoinChannel => $issueTs + $expire,
            ];

            if ($role === self::ROLE_PUBLISHER) {
                $privileges[$privilegePublishAudio] = $issueTs + $expire;
                $privileges[$privilegePublishVideo] = $issueTs + $expire;
                $privileges[$privilegePublishData] = $issueTs + $expire;
            }

            // Pack service privileges
            ksort($privileges);
            $packedPrivileges = '';
            foreach ($privileges as $privId => $privExpire) {
                $packedPrivileges .= pack('v', $privId) . pack('V', $privExpire);
            }
            $packedMap = pack('v', count($privileges)) . $packedPrivileges;

            $uidStr = (string)$uid;
            $servicePayload = pack('v', $serviceRtcType) . $packedMap
                . pack('v', strlen($channelName)) . $channelName
                . pack('v', strlen($uidStr)) . $uidStr;

            $servicesData = $servicePayload;
            $servicesCount = 1;

            $data = pack('v', strlen($appId)) . $appId
                . pack('V', $issueTs)
                . pack('V', $expire)
                . pack('V', $salt)
                . pack('v', $servicesCount)
                . $servicesData;

            // Compute signing key: HMAC-SHA256(HMAC-SHA256(certificate, issueTs), salt)
            $h1 = hash_hmac('sha256', $appCertificate, pack('V', $issueTs), true);
            $signingKey = hash_hmac('sha256', $h1, pack('V', $salt), true);

            // Compute signature
            $signature = hash_hmac('sha256', $data, $signingKey, true);

            $payload = pack('v', strlen($signature)) . $signature . $data;
            $compressed = zlib_encode($payload, ZLIB_ENCODING_DEFLATE);

            return '007' . base64_encode($compressed);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate Agora token: ' . $e->getMessage());
            return null;
        }
    }
}
