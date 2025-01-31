<?php
// app/Helpers/uuid_helper.php

if (! function_exists('generate_uuidv7')) {
    /**
     * Generate a UUIDv7 string.
     *
     * The UUIDv7 is constructed as follows:
     *  - The first 6 bytes (48 bits) are the current Unix timestamp in milliseconds.
     *  - The next 10 bytes (80 bits) are generated randomly.
     *  - The 7th byte’s high nibble is forced to 0x7 (for version 7).
     *  - The 9th byte is modified so that its two most significant bits are 10 (the RFC 4122 variant).
     *
     * @return string The generated UUIDv7 in the standard 8-4-4-4-12 format.
     */
    function generate_uuidv7(): string
    {
        // 1. Get the current time in milliseconds.
        $timeMs = (int) (microtime(true) * 1000);

        // 2. Convert the timestamp to 6 bytes (big-endian).
        $timeBytes = "";
        for ($i = 5; $i >= 0; $i--) {
            $timeBytes .= chr(($timeMs >> ($i * 8)) & 0xff);
        }

        // 3. Generate 10 random bytes.
        $randomBytes = random_bytes(10);

        // 4. Combine to form a 16-byte string.
        $uuidBytes = $timeBytes . $randomBytes;

        // 5. Modify the 7th byte (index 6) to set the version to 7.
        $byteArray = str_split($uuidBytes);
        $byteArray[6] = chr((ord($byteArray[6]) & 0x0f) | (0x7 << 4));

        // 6. Modify the 9th byte (index 8) to set the variant to 10xxxxxx.
        $byteArray[8] = chr((ord($byteArray[8]) & 0x3f) | 0x80);

        $uuidBytes = implode('', $byteArray);
        $hex = bin2hex($uuidBytes);

        // 7. Format into the canonical UUID string: 8-4-4-4-12.
        $uuid = substr($hex, 0, 8) . '-' .
                substr($hex, 8, 4) . '-' .
                substr($hex, 12, 4) . '-' .
                substr($hex, 16, 4) . '-' .
                substr($hex, 20);

        return $uuid;
    }
}
