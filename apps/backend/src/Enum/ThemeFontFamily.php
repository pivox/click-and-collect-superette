<?php

declare(strict_types=1);

namespace App\Enum;

enum ThemeFontFamily: string
{
    case Inter = 'inter';
    case Cairo = 'cairo';
    case Roboto = 'roboto';
    case NotoSansArabic = 'noto_sans_arabic';
    case System = 'system';
}
