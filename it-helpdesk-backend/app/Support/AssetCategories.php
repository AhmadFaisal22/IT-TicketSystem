<?php

namespace App\Support;

class AssetCategories
{
    /** Fixed category keys. Bilingual labels live in the frontend i18n (asset.category.<key>). */
    public const KEYS = [
        'laptop',
        'desktop',
        'monitor',
        'printer',
        'network',
        'phone',
        'peripheral',
        'software_license',
        'other',
    ];

    public const STATUSES = [
        'in_stock',
        'assigned',
        'in_repair',
        'retired',
        'lost',
    ];

    /** Laravel validation rule fragment, e.g. "in:laptop,desktop,...". */
    public static function categoryRule(): string
    {
        return 'in:' . implode(',', self::KEYS);
    }

    public static function statusRule(): string
    {
        return 'in:' . implode(',', self::STATUSES);
    }
}
