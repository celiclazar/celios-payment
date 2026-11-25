<?php

namespace Modules\Payment\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Transparently encrypts gateway settings arrays at rest while remaining
 * backward-compatible with legacy plaintext JSON rows.
 */
class EncryptedArray implements CastsAttributes
{
    public const PREFIX = 'enc:v1:';

    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $raw = (string) $value;

        if (str_starts_with($raw, self::PREFIX)) {
            try {
                $decrypted = Crypt::decryptString(substr($raw, strlen(self::PREFIX)));
                $decoded = json_decode($decrypted, true);

                return is_array($decoded) ? $decoded : [];
            } catch (DecryptException) {
                return [];
            }
        }

        // Legacy plaintext JSON row fallback
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $array = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
        $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::PREFIX.Crypt::encryptString((string) $json);
    }
}
