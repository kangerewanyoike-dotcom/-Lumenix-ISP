<?php

namespace App\Services;

final class Totp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(int $bytes = 20): string
    {
        return $this->encodeBase32(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $key = $this->decodeBase32($secret);
        if ($key === '') {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->codeForCounter($key, $counter + $offset);
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $secret, string $account): string
    {
        $issuer = 'Lumenix';
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private function codeForCounter(string $key, int $counter): string
    {
        $message = pack('N2', intdiv($counter, 0x100000000), $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $value): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';
        foreach (unpack('C*', $value) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::BASE32[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }
        if ($bits > 0) {
            $encoded .= self::BASE32[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(rtrim($value, '='));
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        foreach (str_split($value) as $character) {
            $digit = strpos(self::BASE32, $character);
            if ($digit === false) {
                return '';
            }
            $buffer = ($buffer << 5) | $digit;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xff);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $decoded;
    }
}
