<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CronEditor;
use Hryvinskyi\ConfigurationFields\ViewModel\CronEditorField;
use Magento\TestFramework\Fixture\AppArea;

/**
 * The cron field as the configuration page renders it through the real layout and container: the kit markup, every
 * hook its script uses, escaping, the element's validation classes on the posted value, a value that is not UTF-8,
 * and the inherited state
 */
#[AppArea('adminhtml')]
class CronEditorTest extends FieldRenderTestCase
{
    private const PARTS = ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];

    public function testRendersTheKitMarkupWithEveryHookAndEscapesTheIdAndTheValue(): void
    {
        $html = $this->renderRow(CronEditor::class, ['value' => '*/5 * * * </script>']);
        $xpath = $this->dom($html);
        $rootId = $this->rootId();

        $root = $this->role($xpath, 'hcf-cron');
        self::assertSame(['hui', 'hcf-cron', 'cron-editor-root'], $this->classes($root));
        self::assertSame($rootId, $root->getAttribute('id'));
        self::assertFalse($root->hasAttribute('data-disabled'));
        self::assertSame('status', $this->role($xpath, 'hcf-cron-summary')->getAttribute('role'));

        foreach (self::PARTS as $part) {
            $input = $this->one($xpath, '//input[@data-role="hcf-cron-field"][@data-part="' . $part . '"]');
            self::assertContains('hui-input', $this->classes($input));
            self::assertFalse($input->hasAttribute('disabled'));
            $this->one($xpath, '//label[@for="' . $input->getAttribute('id') . '"]');
            $rows = $this->one($xpath, '//*[@data-role="hcf-cron-syntax"]//tbody[@data-part="' . $part . '"]');
            self::assertTrue($rows->hasAttribute('hidden'));
        }

        $value = $this->role($xpath, 'hcf-cron-value');
        self::assertSame('hidden', $value->getAttribute('type'));
        self::assertSame(self::HTML_ID, $value->getAttribute('id'));
        self::assertSame(self::NAME, $value->getAttribute('name'));
        self::assertSame('*/5 * * * </script>', $value->getAttribute('value'));
        self::assertContains('validate-cron-expression', $this->classes($value));
        self::assertStringContainsString('id="cfg_field&quot;&lt;x&gt;"', $html);

        $config = $this->componentConfig($xpath, $rootId, CronEditorField::COMPONENT);
        self::assertSame('*/5 * * * </script>', $config['value']);
        self::assertSame(self::HTML_ID . '_inherit', $config['inheritId']);
        self::assertIsArray($config['wording']);
        self::assertSame('Invalid cron expression.', $config['wording']['invalidExpression']);
        self::assertSame('This schedule cannot be described in words.', $config['wording']['undescribable']);
        self::assertIsString($config['locale']);
        self::assertMatchesRegularExpression('/^[a-z]{2,3}_[A-Za-z_]*[A-Z]{2}$/', $config['locale']);
    }

    public function testTheHiddenInputCarriesTheElementsValidationClasses(): void
    {
        $xpath = $this->dom($this->renderRow(CronEditor::class, ['value' => '0 1 * * *', 'class' => 'required-entry']));

        $classes = $this->classes($this->role($xpath, 'hcf-cron-value'));
        self::assertContains('validate-cron-expression', $classes);
        self::assertContains('required-entry', $classes);
    }

    public function testAStoredValueThatIsNotUtf8StillRenders(): void
    {
        $xpath = $this->dom($this->renderRow(CronEditor::class, ['value' => "a\xb1"]));

        $value = $this->role($xpath, 'hcf-cron-value')->getAttribute('value');
        self::assertStringStartsWith('a', $value);
        self::assertTrue(mb_check_encoding($value, 'UTF-8'));
        self::assertSame($value, $this->componentConfig($xpath, $this->rootId(), CronEditorField::COMPONENT)['value']);
    }

    public function testAnEmptyFieldIsRenderedEmpty(): void
    {
        $xpath = $this->dom($this->renderRow(CronEditor::class, ['value' => '']));

        self::assertSame('', $this->role($xpath, 'hcf-cron-value')->getAttribute('value'));
        self::assertSame('', $this->componentConfig($xpath, $this->rootId(), CronEditorField::COMPONENT)['value']);
    }

    public function testAnInheritedFieldIsDisabled(): void
    {
        $xpath = $this->dom($this->renderRow(CronEditor::class, [
            'value' => '0 1 * * *',
            'inherit' => 1,
            'can_use_default_value' => true,
            'scope' => 'websites',
        ]));

        self::assertSame('true', $this->role($xpath, 'hcf-cron')->getAttribute('data-disabled'));
        foreach (self::PARTS as $part) {
            self::assertTrue(
                $this->one($xpath, '//input[@data-part="' . $part . '"]')->hasAttribute('disabled')
            );
        }
        self::assertTrue($this->role($xpath, 'hcf-cron-value')->hasAttribute('disabled'));
        self::assertTrue($this->componentConfig($xpath, $this->rootId(), CronEditorField::COMPONENT)['disabled']);
    }
}
