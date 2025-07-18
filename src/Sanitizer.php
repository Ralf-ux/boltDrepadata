<?php
namespace App;

class Sanitizer
{
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        if (is_string($data)) {
            $data = trim($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $data === '' ? null : $data;
        }
        
        return $data;
    }

    public static function sanitizePhone($phone)
    {
        if (empty($phone)) {
            return null;
        }
        
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validate phone number format (9 digits for Cameroon)
        if (strlen($phone) !== 9) {
            return null;
        }
        
        return $phone;
    }

    public static function sanitizeArray($array)
    {
        if (!is_array($array)) {
            return null;
        }
        
        $sanitized = array_map([self::class, 'sanitize'], $array);
        return array_filter($sanitized, function($value) {
            return $value !== null && $value !== '';
        });
    }
}