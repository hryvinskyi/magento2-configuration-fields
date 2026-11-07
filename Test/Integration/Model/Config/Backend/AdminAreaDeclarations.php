<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Magento\Framework\App\Area;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

/**
 * The container declarations of every area as Magento reads them from the modules' `di.xml` files, with test types
 * added to the admin area's alone, as a consumer declares its tag-list field blocks in its `etc/adminhtml/di.xml`
 *
 * Every load is counted by area, so a test can tell whether a save read or applied an area's declarations.
 */
class AdminAreaDeclarations implements ConfigLoaderInterface
{
    /**
     * Area code => how many times its declarations were loaded
     *
     * @var array<string, int>
     */
    private array $loads = [];

    /**
     * @param ConfigLoaderInterface $declarations The real loader
     * @param array<string, array<string, mixed>> $adminTypes Type name => its declaration, and `preferences`, for the
     *     admin area only
     */
    public function __construct(
        private readonly ConfigLoaderInterface $declarations,
        private readonly array $adminTypes
    ) {
    }

    /**
     * The area's declarations, with the test types when the area is the admin's
     *
     * @param string $area
     * @return array<mixed>
     */
    public function load($area)
    {
        $this->loads[$area] = ($this->loads[$area] ?? 0) + 1;
        $declarations = $this->declarations->load($area);

        return $area === Area::AREA_ADMINHTML
            ? array_replace_recursive($declarations, $this->adminTypes)
            : $declarations;
    }

    /**
     * Area code => how many times its declarations were loaded since this object was built
     *
     * @return array<string, int>
     */
    public function loads(): array
    {
        return $this->loads;
    }
}
