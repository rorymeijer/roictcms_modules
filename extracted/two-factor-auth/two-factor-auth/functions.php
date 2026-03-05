<?php
/**
 * Two-Factor Authentication – TOTP helperfuncties (RFC 6238)
 * Geen externe afhankelijkheden – puur PHP.
 */
class TwoFactorAuth
{
    const PERIOD = 30;
    const DIGITS = 6;
    const WINDOW = 1; // Aantal periodes tolerantie voor klokverschil

    /**
     * Genereer een willekeurige Base32-geheime sleutel (16 tekens = 80 bits).
     */
    public static function generateSecret(): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bytes  = random_bytes(16);
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[ord($bytes[$i]) & 0x1F];
        }
        return $secret;
    }

    /**
     * Decodeer een Base32-string naar binaire data.
     */
    public static function base32Decode(string $base32): string
    {
        static $map = [
            'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,
            'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
            'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11,
            'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
            'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27,
            '4' => 28, '5' => 29, '6' => 30, '7' => 31,
        ];

        $base32 = strtoupper(rtrim($base32, '='));
        $buffer = 0;
        $bufLen = 0;
        $output = '';

        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            if (!isset($map[$base32[$i]])) {
                continue;
            }
            $buffer = ($buffer << 5) | $map[$base32[$i]];
            $bufLen += 5;
            if ($bufLen >= 8) {
                $bufLen -= 8;
                $output .= chr(($buffer >> $bufLen) & 0xFF);
            }
        }
        return $output;
    }

    /**
     * Genereer een TOTP-code voor een gegeven geheim en tijdstip.
     */
    public static function generateCode(string $secret, int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $counter = pack('N*', 0) . pack('N*', (int) floor($timestamp / self::PERIOD));
        $key     = self::base32Decode($secret);
        $hash    = hash_hmac('sha1', $counter, $key, true);
        $offset  = ord($hash[19]) & 0x0F;

        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifieer een ingevoerde TOTP-code met kloktolerantie van ±WINDOW periodes.
     */
    public static function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }
        $now = time();
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::generateCode($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Bouw de otpauth://-URI voor QR-code generatie.
     */
    public static function getOtpAuthUri(string $secret, string $email, string $issuer): string
    {
        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($email)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /**
     * Geef de URL terug voor het genereren van een QR-code afbeelding.
     */
    public static function getQrCodeUrl(string $secret, string $email, string $issuer): string
    {
        $uri = self::getOtpAuthUri($secret, $email, $issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data='
            . rawurlencode($uri);
    }
}
