<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\AddonLabelWrapper;
use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Magento\Framework\Escaper;

/**
 * The before and after HTML a field declares, placed around its widget in labels that point at the field
 */
class AddonLabelWrapperTest extends FormElementTestCase
{
    public function testWrapsTheWidgetInTheFieldsLabelsWithTheIdEscaped(): void
    {
        $element = $this->element([
            'html_id' => 'a"b',
            'before_element_html' => '<em>before</em>',
            'after_element_js' => '<script>after()</script>',
            'after_element_html' => '<em>after</em>',
        ]);

        self::assertSame(
            '<label class="addbefore" for="a&quot;b"><em>before</em></label>'
            . '<div>widget</div><script>after()</script>'
            . '<label class="addafter" for="a&quot;b"><em>after</em></label>',
            $this->wrapper()->wrap($element, '<div>widget</div>')
        );
    }

    public function testPrintsOnlyTheWidgetWhenTheFieldDeclaresNothingAround(): void
    {
        self::assertSame(
            '<div>widget</div>',
            $this->wrapper()->wrap($this->element(['html_id' => 'a']), '<div>widget</div>')
        );
    }

    /**
     * The wrapper with an escaper that encodes attributes as Magento's does, without its object manager
     *
     * @return AddonLabelWrapper
     */
    private function wrapper(): AddonLabelWrapper
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtmlAttr')->willReturnCallback(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        return new AddonLabelWrapper(new ElementIdentity(), $escaper);
    }
}
