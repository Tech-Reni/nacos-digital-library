<?php

/**
 * MFA Helpers - TOTP (RFC 6238) Implementation
 * No external dependencies.
 */

class TOTP_Helper {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16) {
        $secret = '';
        while (strlen($secret) < $length) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getOTP($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);

        // Pack time into binary string
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hmac[19]) & 0xf;

        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7fffffff;

        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }

    public static function verifyOTP($secret, $otp, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedOtp = self::getOTP($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedOtp, $otp)) {
                return true;
            }
        }

        return false;
    }

    public static function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
        }
        return $codes;
    }

    private static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        if (!preg_match('/^['.self::$base32Chars.']+$/', $base32)) {
            throw new Exception('Invalid Base32 characters.');
        }

        $buffer = 0;
        $bufferSize = 0;
        $decoded = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $charValue = strpos(self::$base32Chars, $base32[$i]);
            $buffer = ($buffer << 5) | $charValue;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $decoded .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }

        return $decoded;
    }

    public static function getQRCodeUrl($name, $secret, $issuer = 'NACOS App') {
        // We don't have a library for QR codes, so we provide the raw otpauth:// URI
        // The frontend can use a public JS library (like qrcode.js) to render it.
        $encodedName = rawurlencode($name);
        $encodedIssuer = rawurlencode($issuer);
        return "otpauth://totp/{$encodedIssuer}:{$encodedName}?secret={$secret}&issuer={$encodedIssuer}";
    }
}
