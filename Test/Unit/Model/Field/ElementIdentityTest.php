<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;

/**
 * The ids and names of a field's element as the browser reads them, the widget's root id and the field's path
 */
class ElementIdentityTest extends FormElementTestCase
{
    private const HOSTILE_ID = 'tag"list<field>&amp;';

    public function testIdAndNameAreTheTextMagentosEscapedMarkupStandsFor(): void
    {
        $element = $this->element(['html_id' => self::HOSTILE_ID, 'name' => 'groups[a"b][value]'], 'p_');
        $identity = new ElementIdentity();

        // Magento prints an entity already in the id as it is, so the browser reads it as the character
        self::assertSame('p_tag"list<field>&', $identity->htmlId($element));
        self::assertSame('groups[a"b][value]', $identity->name($element));
        self::assertSame('p_tag"list<field>&_inherit', $identity->inheritCheckboxId($element));
    }

    public function testTheRootIdIsStableForOneHtmlIdAndSafeInASelector(): void
    {
        $identity = new ElementIdentity();
        $first = $identity->rootId($this->element(['html_id' => self::HOSTILE_ID]));
        $again = $identity->rootId($this->element(['html_id' => self::HOSTILE_ID]));
        $other = $identity->rootId($this->element(['html_id' => 'another_field']));

        self::assertSame($first, $again);
        self::assertNotSame($first, $other);
        self::assertMatchesRegularExpression('/^hcf-[A-Za-z0-9_-]+$/', $first);
        self::assertMatchesRegularExpression('/^hcf-[A-Za-z0-9_-]+$/', $other);
    }

    public function testTheConfigPathPrefersTheFieldsOwnPathThenGroupAndIdThenTheHtmlId(): void
    {
        $identity = new ElementIdentity();

        self::assertSame(
            'custom/path/value',
            $identity->configPath($this->element([
                'html_id' => 'x',
                'field_config' => ['config_path' => 'custom/path/value', 'path' => 'sec/grp', 'id' => 'fld'],
            ]))
        );
        self::assertSame(
            'sec/grp/fld',
            $identity->configPath(
                $this->element(['html_id' => 'x', 'field_config' => ['path' => 'sec/grp', 'id' => 'fld']])
            )
        );
        self::assertSame('x', $identity->configPath($this->element(['html_id' => 'x'])));
    }
}
