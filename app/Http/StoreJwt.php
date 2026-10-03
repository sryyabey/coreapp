<?php

namespace App\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;

class StoreJwt
{
    public static function encode(array $header, array $claims, string $key): string
    {
        $privateKey = openssl_pkey_get_private($key);
        $details = $privateKey ? openssl_pkey_get_details($privateKey) : false;
        abort_unless($details && (($header['alg'] === 'ES256' && ($details['ec']['curve_name'] ?? '') === 'prime256v1') || ($header['alg'] === 'RS256' && $details['type'] === OPENSSL_KEYTYPE_RSA)), 503, 'Mağaza imzalama anahtarı geçersiz.');
        $input = self::base64(json_encode($header, JSON_THROW_ON_ERROR)).'.'.self::base64(json_encode($claims, JSON_THROW_ON_ERROR));
        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new HttpException(503, 'Mağaza imzalama yapılandırması geçersiz.');
        }
        if ($header['alg'] === 'ES256') {
            $offset = 2;
            if (ord($signature[1]) & 0x80) {
                $offset += ord($signature[1]) & 0x7F;
            }
            $offset++;
            $length = ord($signature[$offset++]);
            $r = substr($signature, $offset, $length);
            $offset += $length + 1;
            $length = ord($signature[$offset++]);
            $s = substr($signature, $offset, $length);
            $signature = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
        }

        return $input.'.'.self::base64($signature);
    }

    public static function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** Only decode JWS returned directly by Apple's authenticated HTTPS API; never accept client JWS here. */
    public static function appleResponsePayload(string $jws): array
    {
        $parts = explode('.', $jws);
        abort_unless(count($parts) === 3, 502, 'Geçersiz Apple yanıtı.');
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        abort_unless(($header['alg'] ?? null) === 'ES256' && is_array($payload), 502, 'Geçersiz Apple yanıtı.');

        return $payload;
    }
}
