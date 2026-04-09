<?php

declare(strict_types=1);

namespace App\Support;

final class Html
{
    public static function stripSummary(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        return trim(strip_tags($html));
    }
}

