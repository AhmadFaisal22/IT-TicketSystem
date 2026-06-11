<?php

namespace App\Support;

class AssetCategories
{
    /**
     * Default categories seeded into the admin-managed asset_categories table.
     * Maps the legacy hardcoded key to [English name, Chinese name].
     */
    public const DEFAULTS = [
        'laptop'           => ['Laptop', '笔记本'],
        'desktop'          => ['Desktop', '台式机'],
        'monitor'          => ['Monitor', '显示器'],
        'printer'          => ['Printer', '打印机'],
        'network'          => ['Network Device', '网络设备'],
        'phone'            => ['Phone', '手机'],
        'peripheral'       => ['Peripheral', '外设'],
        'software_license' => ['Software License', '软件许可'],
        'other'            => ['Other', '其他'],
    ];

    public const STATUSES = [
        'in_stock',
        'assigned',
        'in_repair',
        'retired',
        'lost',
    ];

    /** Categories now live in the asset_categories table — validate against it. */
    public static function categoryRule(): string
    {
        return 'exists:asset_categories,name';
    }

    public static function statusRule(): string
    {
        return 'in:' . implode(',', self::STATUSES);
    }
}
