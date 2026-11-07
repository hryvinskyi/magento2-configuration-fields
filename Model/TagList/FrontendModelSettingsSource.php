<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field;

/**
 * A tag-list field's settings read from its frontend model, the block that shows the field in the browser
 *
 * The field is found by its configuration path (a field with a `config_path` of its own included) in the admin
 * configuration structure, the one read from the modules' `etc/adminhtml/system.xml` files whatever area the code
 * runs in. Its `frontend_model` gives the settings only when it is the tag-list field block, a subclass of it or a
 * virtual type of either; they are the `tagListConfig` argument the admin area declares for it
 * (FieldBlockConfigInterface), so a save from the admin, the storefront, a cron job, a setup patch or the command line
 * checks a value against the same pattern. Any other field, and a path no field is declared under, give null.
 */
class FrontendModelSettingsSource implements FieldSettingsSourceInterface
{
    /**
     * @param Structure $structure The admin area's configuration structure
     * @param FieldBlockConfigInterface $fieldBlocks
     * @param SettingsReader $settingsReader
     */
    public function __construct(
        private readonly Structure $structure,
        private readonly FieldBlockConfigInterface $fieldBlocks,
        private readonly SettingsReader $settingsReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forPath(string $configPath): ?Settings
    {
        $field = $this->structure->getElementByConfigPath($configPath);
        if (!$field instanceof Field) {
            return null;
        }
        $frontendModel = ltrim((string) $field->getFrontendModel(), '\\');
        if ($frontendModel === '') {
            return null;
        }
        $tagListConfig = $this->fieldBlocks->tagListConfig($frontendModel);

        return $tagListConfig === null ? null : $this->settingsReader->read($tagListConfig);
    }
}
