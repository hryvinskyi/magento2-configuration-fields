<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend\Fixture;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList as TagListField;
use Magento\Backend\Block\Template\Context;

/**
 * A tag-list field block that replaces its declared `tagListConfig` in code, with a pattern no value matches
 *
 * A save checks values against the declared argument only, so a block that changes its settings in code is not seen
 * by it.
 */
class PresetTagListField extends TagListField
{
    /**
     * @param Context $context
     * @param array<mixed> $tagListConfig The declared settings, replaced
     * @param array<string,mixed> $data
     */
    public function __construct(Context $context, array $tagListConfig = [], array $data = [])
    {
        parent::__construct($context, ['validation' => '^never$'] + $tagListConfig, $data);
    }
}
