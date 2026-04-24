<?php

namespace AliMPay\Core;

use Exception;

class WebAuthn
{
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new Exception('Invalid base64url value');
        }

        return $decoded;
    }

    public static function relyingPartyId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return strtolower(preg_replace('/:\d+$/', '', $host));
    }

    public static function relyingPartyName(): string
    {
        return 'AliMPay';
    }

    public static function expectedOrigin(): string
    {
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            return (string)$_SERVER['HTTP_ORIGIN'];
        }

        $proto = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $proto = 'https';
        }

        return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public static function createChallenge(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    public static function registrationOptions(string $challenge, array $passkeys): array
    {
        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => self::relyingPartyName(),
                'id' => self::relyingPartyId(),
            ],
            'user' => [
                'id' => self::base64UrlEncode(hash('sha256', 'alimpay-admin', true)),
                'name' => 'admin',
                'displayName' => 'AliMPay Admin',
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
            ],
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'excludeCredentials' => array_map(static fn($key) => [
                'type' => 'public-key',
                'id' => $key['id'],
            ], self::validPasskeys($passkeys)),
            'timeout' => 60000,
            'attestation' => 'none',
        ];
    }

    public static function authenticationOptions(string $challenge, array $passkeys): array
    {
        return [
            'challenge' => $challenge,
            'rpId' => self::relyingPartyId(),
            'allowCredentials' => array_map(static fn($key) => [
                'type' => 'public-key',
                'id' => $key['id'],
            ], self::validPasskeys($passkeys)),
            'timeout' => 60000,
            'userVerification' => 'required',
        ];
    }

    public static function verifyRegistration(array $credential, string $challenge): array
    {
        $clientDataJson = self::requiredDecoded($credential, ['response', 'clientDataJSON']);
        $clientData = self::verifyClientData($clientDataJson, 'webauthn.create', $challenge);

        $attestationObject = self::requiredDecoded($credential, ['response', 'attestationObject']);
        [$attestation] = self::decodeCbor($attestationObject);
        if (!is_array($attestation) || empty($attestation['authData'])) {
            throw new Exception('Invalid passkey attestation data');
        }

        $authData = $attestation['authData'];
        $parsed = self::parseAuthenticatorData($authData, true);
        self::verifyRpIdHash($parsed['rp_id_hash']);
        self::verifyUserPresence($parsed['flags']);
        self::verifyUserVerification($parsed['flags']);

        $credentialId = self::base64UrlEncode($parsed['credential_id']);
        if (!hash_equals($credentialId, (string)($credential['rawId'] ?? ''))) {
            throw new Exception('Passkey credential id mismatch');
        }

        return [
            'id' => $credentialId,
            'public_key' => self::base64UrlEncode($parsed['credential_public_key']),
            'sign_count' => $parsed['sign_count'],
            'client_origin' => $clientData['origin'] ?? '',
        ];
    }

    public static function verifyAuthentication(array $credential, array $passkeys, string $challenge): array
    {
        $credentialId = (string)($credential['rawId'] ?? '');
        $passkey = self::findPasskey($passkeys, $credentialId);
        if ($passkey === null) {
            throw new Exception('Unknown passkey');
        }

        $clientDataJson = self::requiredDecoded($credential, ['response', 'clientDataJSON']);
        self::verifyClientData($clientDataJson, 'webauthn.get', $challenge);

        $authenticatorData = self::requiredDecoded($credential, ['response', 'authenticatorData']);
        $signature = self::requiredDecoded($credential, ['response', 'signature']);
        $parsed = self::parseAuthenticatorData($authenticatorData, false);
        self::verifyRpIdHash($parsed['rp_id_hash']);
        self::verifyUserPresence($parsed['flags']);
        self::verifyUserVerification($parsed['flags']);

        $signedData = $authenticatorData . hash('sha256', $clientDataJson, true);
        $publicKey = self::base64UrlDecode((string)$passkey['public_key']);
        if (!self::verifySignature($signedData, $signature, $publicKey)) {
            throw new Exception('Passkey signature verification failed');
        }

        $previousCount = (int)($passkey['sign_count'] ?? 0);
        if ($previousCount > 0 && $parsed['sign_count'] > 0 && $parsed['sign_count'] <= $previousCount) {
            throw new Exception('Passkey sign counter is invalid');
        }

        return [
            'id' => $credentialId,
            'sign_count' => $parsed['sign_count'],
        ];
    }

    public static function publicKeySummary(array $passkey): array
    {
        return [
            'id' => $passkey['id'] ?? '',
            'name' => $passkey['name'] ?? '未命名 Passkey',
            'created_at' => $passkey['created_at'] ?? '',
            'last_used_at' => $passkey['last_used_at'] ?? '',
        ];
    }

    public static function validPasskeys(array $passkeys): array
    {
        return array_values(array_filter($passkeys, static fn($key) => !empty($key['id']) && !empty($key['public_key'])));
    }

    private static function requiredDecoded(array $credential, array $path): string
    {
        $value = $credential;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new Exception('Missing passkey response field');
            }
            $value = $value[$segment];
        }

        return self::base64UrlDecode((string)$value);
    }

    private static function verifyClientData(string $clientDataJson, string $type, string $challenge): array
    {
        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData)) {
            throw new Exception('Invalid passkey client data');
        }
        if (($clientData['type'] ?? '') !== $type) {
            throw new Exception('Invalid passkey operation type');
        }
        if (!hash_equals($challenge, (string)($clientData['challenge'] ?? ''))) {
            throw new Exception('Invalid passkey challenge');
        }
        if (!hash_equals(self::expectedOrigin(), (string)($clientData['origin'] ?? ''))) {
            throw new Exception('Invalid passkey origin');
        }

        return $clientData;
    }

    private static function verifyRpIdHash(string $rpIdHash): void
    {
        if (!hash_equals(hash('sha256', self::relyingPartyId(), true), $rpIdHash)) {
            throw new Exception('Invalid passkey relying party');
        }
    }

    private static function verifyUserPresence(int $flags): void
    {
        if (($flags & 0x01) !== 0x01) {
            throw new Exception('Passkey user presence is required');
        }
    }

    private static function verifyUserVerification(int $flags): void
    {
        if (($flags & 0x04) !== 0x04) {
            throw new Exception('Passkey user verification is required');
        }
    }

    private static function parseAuthenticatorData(string $authData, bool $requireAttestedCredential): array
    {
        if (strlen($authData) < 37) {
            throw new Exception('Invalid authenticator data');
        }

        $offset = 0;
        $rpIdHash = substr($authData, $offset, 32);
        $offset += 32;
        $flags = ord($authData[$offset]);
        $offset += 1;
        $signCount = unpack('N', substr($authData, $offset, 4))[1];
        $offset += 4;

        $result = [
            'rp_id_hash' => $rpIdHash,
            'flags' => $flags,
            'sign_count' => $signCount,
        ];

        if (!$requireAttestedCredential) {
            return $result;
        }

        if (($flags & 0x40) !== 0x40) {
            throw new Exception('Missing attested credential data');
        }
        if (strlen($authData) < $offset + 18) {
            throw new Exception('Invalid attested credential data');
        }

        $offset += 16;
        $credentialIdLength = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        $credentialId = substr($authData, $offset, $credentialIdLength);
        $offset += $credentialIdLength;
        $credentialPublicKey = substr($authData, $offset);

        [$coseKey] = self::decodeCbor($credentialPublicKey);
        if (!is_array($coseKey) || ($coseKey[3] ?? null) !== -7) {
            throw new Exception('Only ES256 passkeys are supported');
        }

        $result['credential_id'] = $credentialId;
        $result['credential_public_key'] = $credentialPublicKey;

        return $result;
    }

    private static function findPasskey(array $passkeys, string $credentialId): ?array
    {
        foreach (self::validPasskeys($passkeys) as $passkey) {
            if (hash_equals((string)$passkey['id'], $credentialId)) {
                return $passkey;
            }
        }

        return null;
    }

    private static function verifySignature(string $data, string $signature, string $cosePublicKey): bool
    {
        [$key] = self::decodeCbor($cosePublicKey);
        if (!is_array($key) || ($key[1] ?? null) !== 2 || ($key[3] ?? null) !== -7 || ($key[-1] ?? null) !== 1) {
            throw new Exception('Unsupported passkey public key');
        }

        $x = $key[-2] ?? '';
        $y = $key[-3] ?? '';
        if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
            throw new Exception('Invalid passkey public key coordinates');
        }

        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004') . $x . $y;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_verify($data, $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function decodeCbor(string $data, int $offset = 0): array
    {
        if ($offset >= strlen($data)) {
            throw new Exception('Invalid CBOR data');
        }

        $initial = ord($data[$offset++]);
        $major = $initial >> 5;
        $additional = $initial & 0x1f;
        [$length, $offset] = self::readCborLength($data, $offset, $additional);

        switch ($major) {
            case 0:
                return [$length, $offset];
            case 1:
                return [-1 - $length, $offset];
            case 2:
                self::ensureCborLength($data, $offset, $length);
                return [substr($data, $offset, $length), $offset + $length];
            case 3:
                self::ensureCborLength($data, $offset, $length);
                return [substr($data, $offset, $length), $offset + $length];
            case 4:
                $items = [];
                for ($i = 0; $i < $length; $i++) {
                    [$items[], $offset] = self::decodeCbor($data, $offset);
                }
                return [$items, $offset];
            case 5:
                $map = [];
                for ($i = 0; $i < $length; $i++) {
                    [$key, $offset] = self::decodeCbor($data, $offset);
                    [$value, $offset] = self::decodeCbor($data, $offset);
                    $map[$key] = $value;
                }
                return [$map, $offset];
            case 6:
                return self::decodeCbor($data, $offset);
            case 7:
                return [self::decodeCborSimpleValue($additional, $length), $offset];
            default:
                throw new Exception('Unsupported CBOR type');
        }
    }

    private static function readCborLength(string $data, int $offset, int $additional): array
    {
        if ($additional < 24) {
            return [$additional, $offset];
        }

        $bytes = [24 => 1, 25 => 2, 26 => 4, 27 => 8][$additional] ?? null;
        if ($bytes === null) {
            throw new Exception('Unsupported CBOR length');
        }
        self::ensureCborLength($data, $offset, $bytes);
        $raw = substr($data, $offset, $bytes);
        $offset += $bytes;

        if ($bytes === 1) {
            return [ord($raw), $offset];
        }
        if ($bytes === 2) {
            return [unpack('n', $raw)[1], $offset];
        }
        if ($bytes === 4) {
            return [unpack('N', $raw)[1], $offset];
        }

        $parts = unpack('Nhigh/Nlow', $raw);
        return [($parts['high'] * 4294967296) + $parts['low'], $offset];
    }

    private static function ensureCborLength(string $data, int $offset, int $length): void
    {
        if ($length < 0 || strlen($data) < $offset + $length) {
            throw new Exception('Truncated CBOR data');
        }
    }

    private static function decodeCborSimpleValue(int $additional, int $value)
    {
        if ($additional === 20) {
            return false;
        }
        if ($additional === 21) {
            return true;
        }
        if ($additional === 22) {
            return null;
        }

        return $value;
    }
}
