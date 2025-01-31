<?php

namespace App\Validation;

class CustomRules
{
    /**
     * Validates that a string is a valid UUID version 7.
     *
     * @param string $str    The input string (UUID candidate).
     *
     * @return bool True if valid UUID v7, false otherwise.
     */
    public function valid_uuidv7(string $str): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $str
        );
    }
}
