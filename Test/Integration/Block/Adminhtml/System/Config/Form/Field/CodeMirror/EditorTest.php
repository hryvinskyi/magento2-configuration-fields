<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field\CodeMirror;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CodeMirror\CssEditor;
use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CodeMirror\Editor;
use Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field\FieldRenderTestCase;
use Hryvinskyi\ConfigurationFields\ViewModel\CodeMirrorField;
use Magento\TestFramework\Fixture\AppArea;

/**
 * The code field as the configuration page renders it through the real layout and container: the kit root, the
 * textarea that keeps stored entities as text, a value that is not UTF-8, the editor settings, and the data argument
 * reaching a subclass with a constructor of its own
 */
#[AppArea('adminhtml')]
class EditorTest extends FieldRenderTestCase
{
    private const STORED = '&lt;b&gt; </textarea><script>alert(1)</script>';

    public function testRendersTheKitRootAndKeepsTheStoredTextAsText(): void
    {
        $html = $this->renderRow(Editor::class, ['value' => self::STORED]);
        $xpath = $this->dom($html);
        $rootId = $this->rootId();

        $root = $this->role($xpath, 'hcf-code');
        self::assertSame(['hui', 'hcf-code', 'codemirror-editor-root'], $this->classes($root));
        self::assertSame($rootId, $root->getAttribute('id'));
        self::assertFalse($root->hasAttribute('data-disabled'));

        $textarea = $this->one($xpath, '//*[@data-role="hcf-code"]/textarea');
        self::assertSame(['admin__control-textarea', 'codemirror-textarea'], $this->classes($textarea));
        self::assertSame(self::HTML_ID, $textarea->getAttribute('id'));
        self::assertSame(self::NAME, $textarea->getAttribute('name'));
        self::assertSame(self::STORED, $textarea->textContent);
        self::assertStringContainsString('id="cfg_field&quot;&lt;x&gt;"', $html);

        $config = $this->componentConfig($xpath, $rootId, CodeMirrorField::COMPONENT);
        self::assertSame('htmlmixed', $config['mode']);
        self::assertSame(self::HTML_ID, $config['inputId']);
        self::assertFalse($config['readOnly']);
    }

    public function testASubclassWithItsOwnConstructorGetsTheDataItemsAndItsMode(): void
    {
        $xpath = $this->dom($this->renderRow(CssEditor::class, ['value' => 'a { color: red; }']));

        self::assertSame('a { color: red; }', $this->one($xpath, '//*[@data-role="hcf-code"]/textarea')->textContent);
        self::assertSame(
            'css',
            $this->componentConfig($xpath, $this->rootId(), CodeMirrorField::COMPONENT)['mode']
        );
    }

    public function testAStoredValueThatIsNotUtf8StillRenders(): void
    {
        $xpath = $this->dom($this->renderRow(Editor::class, ['value' => "a\xb1"]));

        $text = $this->one($xpath, '//*[@data-role="hcf-code"]/textarea')->textContent;
        self::assertStringStartsWith('a', $text);
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    public function testAnInheritedFieldOpensReadOnly(): void
    {
        $xpath = $this->dom($this->renderRow(Editor::class, [
            'value' => 'x',
            'inherit' => 1,
            'can_use_default_value' => true,
            'scope' => 'websites',
        ]));

        self::assertSame('true', $this->role($xpath, 'hcf-code')->getAttribute('data-disabled'));
        self::assertTrue($this->one($xpath, '//*[@data-role="hcf-code"]/textarea')->hasAttribute('disabled'));
        self::assertTrue($this->componentConfig($xpath, $this->rootId(), CodeMirrorField::COMPONENT)['readOnly']);
    }
}
