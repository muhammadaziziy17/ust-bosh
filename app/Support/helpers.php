<?php

/**
 * Somda pul summasini chiroyli formatlash.
 * Misol: 150000 -> "150 000 so‘m"
 */
if (! function_exists('format_som')) {
    function format_som(int|float|string $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' so‘m';
    }
}
