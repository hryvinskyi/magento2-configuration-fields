<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\ViewModel;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Hryvinskyi\ConfigurationFields\ViewModel\CodeMirrorField;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

/**
 * The code field's data: the stored code as stored, the editor settings, read-only while disabled, and the mount of
 * the editor
 */
class CodeMirrorFieldTest extends FormElementTestCase
{
    public function testCarriesTheStoredCodeAndTheEditorSettings(): void
    {
        $element = $this->element(
            ['html_id' => 'css', 'name' => 'groups[g][fields][css][value]', 'value' => '&lt;b&gt;']
        );
        $rootId = (new ElementIdentity())->rootId($element);
        $config = $this->viewModel()->config(
            $element,
            ['mode' => 'css', 'theme' => 'monokai', 'lineNumbers' => false, 'lineWrapping' => true]
        );

        self::assertSame('css', $config['inputId']);
        self::assertSame('groups[g][fields][css][value]', $config['inputName']);
        self::assertSame('css_inherit', $config['inheritId']);
        self::assertSame('&lt;b&gt;', $config['value']);
        self::assertFalse($config['disabled']);
        self::assertSame(
            [
                '#' . $rootId => [
                    CodeMirrorField::COMPONENT => [
                        'inputId' => 'css',
                        'inheritId' => 'css_inherit',
                        'mode' => 'css',
                        'theme' => 'monokai',
                        'lineNumbers' => false,
                        'lineWrapping' => true,
                        'readOnly' => false,
                    ],
                ],
            ],
            $config['mount']['init']
        );
    }

    public function testDefaultsAndReadOnlyWhileDisabled(): void
    {
        $element = $this->element(['html_id' => 'html', 'can_use_default_value' => true, 'disabled' => true]);
        $config = $this->viewModel()->config($element, []);
        $component = array_values($config['mount']['init'])[0][CodeMirrorField::COMPONENT];

        self::assertTrue($config['disabled']);
        self::assertSame('htmlmixed', $component['mode']);
        self::assertSame('default', $component['theme']);
        self::assertTrue($component['lineNumbers']);
        self::assertTrue($component['lineWrapping']);
        self::assertTrue($component['readOnly']);
    }

    public function testCouldInheritIsNotReadOnly(): void
    {
        $element = $this->element(
            ['html_id' => 'html', 'can_use_default_value' => true, 'can_use_website_value' => true]
        );

        self::assertFalse($this->viewModel()->config($element, [])['disabled']);
    }

    public function testAStoredValueThatIsNotUtf8IsPrintable(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'html', 'value' => "a\xb1"]), []);

        self::assertTrue(mb_check_encoding($config['value'], 'UTF-8'));
        self::assertStringStartsWith('a', $config['value']);
    }

    /**
     * @return CodeMirrorField
     */
    private function viewModel(): CodeMirrorField
    {
        $identity = new ElementIdentity();

        return new CodeMirrorField($identity, new StoredValue(), new WidgetMount($identity, new JsonHexTag()));
    }
}
