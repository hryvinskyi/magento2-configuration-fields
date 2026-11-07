<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Magento\Framework\Exception\LocalizedException;

/**
 * The settings a tag-list field shows in the browser, found by the field's configuration path, so its save checks
 * the values against the same pattern, case rule and message
 */
interface FieldSettingsSourceInterface
{
    /**
     * The settings of the tag-list field stored under a configuration path
     *
     * Null when no field is declared there, or the field's frontend model is not a tag list.
     *
     * @param string $configPath
     * @return Settings|null
     * @throws LocalizedException When the field's settings name an empty separator
     */
    public function forPath(string $configPath): ?Settings;
}
