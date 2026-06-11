<?php

namespace Tests\Feature;

use App\Support\AssetCategories;
use Tests\TestCase;

class AssetTest extends TestCase
{
    public function test_category_and_status_rules_are_built_from_constants(): void
    {
        $this->assertSame('in:laptop,desktop,monitor,printer,network,phone,peripheral,software_license,other', AssetCategories::categoryRule());
        $this->assertSame('in:in_stock,assigned,in_repair,retired,lost', AssetCategories::statusRule());
    }
}
