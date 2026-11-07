<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

/**
 * The `tagListConfig` argument declared for a field's frontend model, as the admin area's declarations give it
 *
 * Only the declared argument counts: a block class that changes its settings in code (a subclass's constructor that
 * replaces or fills the argument) is not consulted, so a value is accepted or refused the same way in every area.
 */
interface FieldBlockConfigInterface
{
    /**
     * The declared `tagListConfig`, or null when the frontend model is not a tag list
     *
     * A tag list is the tag-list field block, a subclass of it or a virtual type of either.
     *
     * @param string $frontendModel A class or a virtual type name, without a leading backslash
     * @return array<mixed>|null
     */
    public function tagListConfig(string $frontendModel): ?array;
}
