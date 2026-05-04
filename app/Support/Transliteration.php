<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cyrillic (and other scripts) → Latin ASCII for URL slugs. Uses ext-intl when available.
 */
final class Transliteration
{
    /**
     * Convert text to Latin letters and digits-friendly ASCII; non-letters may remain as spaces later.
     */
    public static function toLatinAscii(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($tr !== null) {
                $out = $tr->transliterate($s);
                if (is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }

        $mapped = strtr(mb_strtolower($s), self::CYRILLIC_TO_LATIN);

        return $mapped;
    }

    /** Lowercase Russian Cyrillic → Latin (single-letter approximation). */
    private const CYRILLIC_TO_LATIN = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];
}
