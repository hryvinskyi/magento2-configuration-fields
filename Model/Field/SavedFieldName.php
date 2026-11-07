<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Field;

use Magento\Framework\DataObject;

/**
 * The name a refused save gives the field a backend model is saving, so the person knows which field to fix
 *
 * The field's label, translated, when the value is saved through the configuration form, which hands the backend
 * model the field's definition; otherwise, as from the command line, the configuration path.
 */
class SavedFieldName
{
    /**
     * The field's label, or its configuration path when the save carries no label
     *
     * @param DataObject $value The backend model being saved
     * @return string
     */
    public function of(DataObject $value): string
    {
        $fieldConfig = $value->getData('field_config');
        $label = is_array($fieldConfig) ? ($fieldConfig['label'] ?? null) : null;
        if ((is_string($label) || $label instanceof \Stringable) && trim((string) $label) !== '') {
            return (string) __((string) $label);
        }
        $path = $value->getData('path');

        return is_scalar($path) ? (string) $path : '';
    }
}
