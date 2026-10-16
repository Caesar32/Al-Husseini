<?php

namespace App\Support;

/** Element ids and validation keys derived from form field names (items[0][qty]). */
final class FormIds
{
    /** "items[0][qty]" -> "items-0-qty" */
    public static function id(string $name): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    }

    /** "items[0][qty]" -> "items.0.qty" (the key used by old() and the error bag) */
    public static function errorKey(string $name): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }
}
