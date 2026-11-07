<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\SavedFieldName;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

/**
 * The name a refused save gives its field: the label the configuration form hands over, else the path
 */
class SavedFieldNameTest extends TestCase
{
    public function testTheLabelOfTheFieldTheFormSaves(): void
    {
        $value = new DataObject([
            'path' => 'section/group/field',
            'field_config' => ['id' => 'field', 'label' => 'Excluded IPs'],
        ]);

        self::assertSame('Excluded IPs', (new SavedFieldName())->of($value));
    }

    public function testThePathWhenTheSaveCarriesNoLabel(): void
    {
        self::assertSame(
            'section/group/field',
            (new SavedFieldName())->of(new DataObject(['path' => 'section/group/field']))
        );
        self::assertSame(
            'section/group/field',
            (new SavedFieldName())->of(new DataObject([
                'path' => 'section/group/field',
                'field_config' => ['label' => '  '],
            ]))
        );
    }
}
