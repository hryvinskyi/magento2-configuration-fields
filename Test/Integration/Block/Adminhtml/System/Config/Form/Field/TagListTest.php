<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList;
use Hryvinskyi\ConfigurationFields\ViewModel\TagListField;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The tag-list field as the configuration page renders it through the real layout and container: the kit markup and
 * every hook its script uses, escaping, the values per save type, a stored value that cannot be read kept with a
 * notice, a value that is not UTF-8, the inherited state, and the data argument reaching a consumer's virtual type
 */
#[AppArea('adminhtml')]
class TagListTest extends FieldRenderTestCase
{
    public function testRendersTheKitMarkupWithEveryHookAndEscapesTheIdAndTheValue(): void
    {
        $html = $this->renderRow(TagList::class, ['value' => "</script>\nB"]);
        $xpath = $this->dom($html);
        $rootId = $this->rootId();

        $root = $this->role($xpath, 'hcf-tag-list');
        self::assertSame(['hui', 'hcf-tag-list', 'tag-list-root'], $this->classes($root));
        self::assertSame($rootId, $root->getAttribute('id'));
        self::assertFalse($root->hasAttribute('data-disabled'));

        $value = $this->role($xpath, 'hcf-tag-list-value');
        self::assertSame('hidden', $value->getAttribute('type'));
        self::assertSame(self::HTML_ID, $value->getAttribute('id'));
        self::assertSame(self::NAME, $value->getAttribute('name'));
        self::assertSame("</script>\nB", $value->getAttribute('value'));
        self::assertSame(0, $this->matchCount($xpath, '//*[@data-role="hcf-tag-list-unread"]'));
        self::assertStringContainsString('id="cfg_field&quot;&lt;x&gt;"', $html);
        self::assertSame(
            self::HTML_ID,
            $this->one($xpath, '//td[@class="label"]/label')->getAttribute('for'),
            'The id matches the one Magento prints in the field label'
        );

        $input = $this->role($xpath, 'hcf-tag-list-input');
        self::assertContains('hui-input', $this->classes($input));
        self::assertFalse($input->hasAttribute('disabled'));
        self::assertSame('Type value and press Enter...', $input->getAttribute('placeholder'));
        $search = $this->role($xpath, 'hcf-tag-list-search');
        self::assertContains('hui-input', $this->classes($search));
        self::assertTrue($search->hasAttribute('hidden'));
        $error = $this->role($xpath, 'hcf-tag-list-error');
        self::assertSame('alert', $error->getAttribute('role'));
        self::assertTrue($error->hasAttribute('hidden'));
        $this->role($xpath, 'hcf-tag-list-chips');
        $this->one($xpath, '//template[@data-role="hcf-tag-list-chip-template"]//*[@data-role="hcf-tag-list-chip"]');
        $this->one(
            $xpath,
            '//template[@data-role="hcf-tag-list-chip-template"]//button[@data-role="hcf-tag-list-remove"]'
        );

        $config = $this->componentConfig($xpath, $rootId, TagListField::COMPONENT);
        self::assertSame(['</script>', 'B'], $config['values']);
        self::assertSame(self::HTML_ID, $config['inputId']);
        self::assertSame(self::HTML_ID . '_inherit', $config['inheritId']);
        self::assertFalse($config['disabled']);
    }

    /**
     * @param array<string,string> $tagListConfig
     * @param string $stored
     * @param list<string> $values
     * @param string $posted
     * @return void
     */
    #[DataProvider('saveTypes')]
    public function testTheDecodedValuesReachTheConfigAndTheHiddenInput(
        array $tagListConfig,
        string $stored,
        array $values,
        string $posted
    ): void {
        $xpath = $this->dom($this->renderRow($this->tagListType($tagListConfig), ['value' => $stored]));

        self::assertSame($posted, $this->role($xpath, 'hcf-tag-list-value')->getAttribute('value'));
        self::assertSame(
            $values,
            $this->componentConfig($xpath, $this->rootId(), TagListField::COMPONENT)['values']
        );
        self::assertSame(0, $this->matchCount($xpath, '//*[@data-role="hcf-tag-list-unread"]'));
    }

    /**
     * @return array<string, array{array<string, string>, string, list<string>, string}>
     */
    public static function saveTypes(): array
    {
        return [
            'json' => [['save_type' => 'json'], '["A","b c","0"]', ['A', 'b c', '0'], "A\nb c\n0"],
            'serialized' => [
                ['save_type' => 'serialized', 'separator' => ','],
                'a:2:{i:0;s:1:"A";i:1;s:3:"b c";}',
                ['A', 'b c'],
                'A,b c',
            ],
            'separator' => [['save_type' => 'separator', 'separator' => ';'], 'A; B;;', ['A', 'B'], 'A;B'],
            'json list of numbers' => [['save_type' => 'json'], '[8080,8443]', ['8080', '8443'], "8080\n8443"],
            'empty json list' => [['save_type' => 'json'], '[]', [], ''],
            'empty serialized list' => [['save_type' => 'serialized'], 'a:0:{}', [], ''],
        ];
    }

    /**
     * @param string $stored
     * @return void
     */
    #[DataProvider('unreadableJson')]
    public function testAStoredValueThatCannotBeReadIsKeptInTheHiddenInputWithANotice(string $stored): void
    {
        $xpath = $this->dom($this->renderRow($this->tagListType(['save_type' => 'json']), ['value' => $stored]));
        $rootId = $this->rootId();

        self::assertSame($stored, $this->role($xpath, 'hcf-tag-list-value')->getAttribute('value'));
        self::assertSame([], $this->componentConfig($xpath, $rootId, TagListField::COMPONENT)['values']);
        $notice = $this->one(
            $xpath,
            '//*[@id="' . $rootId . '"]//*[@data-role="hcf-tag-list-unread"]'
        );
        self::assertContains('hui-notice', $this->classes($notice));
        self::assertSame('warning', $notice->getAttribute('data-tone'));
        self::assertSame(
            'The stored value could not be read as a list. It is kept as it is until you add or remove a value here.',
            trim($notice->textContent)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableJson(): array
    {
        return [
            'values joined by a newline' => ["A\nB"],
            'corrupted json' => ['["A",'],
        ];
    }

    public function testAStoredValueThatIsNotUtf8StillRenders(): void
    {
        $xpath = $this->dom($this->renderRow(TagList::class, ['value' => "a\xb1"]));

        $posted = $this->role($xpath, 'hcf-tag-list-value')->getAttribute('value');
        self::assertStringStartsWith('a', $posted);
        self::assertTrue(mb_check_encoding($posted, 'UTF-8'));
        self::assertSame(
            [$posted],
            $this->componentConfig($xpath, $this->rootId(), TagListField::COMPONENT)['values']
        );
    }

    public function testAnInheritedFieldIsDisabledAndPostsNoValue(): void
    {
        $xpath = $this->dom($this->renderRow(TagList::class, [
            'value' => 'A',
            'inherit' => 1,
            'can_use_default_value' => true,
            'scope' => 'websites',
        ]));

        self::assertSame('true', $this->role($xpath, 'hcf-tag-list')->getAttribute('data-disabled'));
        self::assertTrue($this->role($xpath, 'hcf-tag-list-input')->hasAttribute('disabled'));
        self::assertTrue($this->role($xpath, 'hcf-tag-list-value')->hasAttribute('disabled'));
        $config = $this->componentConfig($xpath, $this->rootId(), TagListField::COMPONENT);
        self::assertTrue($config['disabled']);
        self::assertSame(
            $config['inheritId'],
            $this->one($xpath, '//input[@type="checkbox"]')->getAttribute('id'),
            'The config names the checkbox Magento prints'
        );
    }

    public function testABlockWithoutItsViewModelSaysWhichDataItemIsMissing(): void
    {
        $block = Bootstrap::getObjectManager()->create(TagList::class, ['data' => []]);
        self::assertInstanceOf(TagList::class, $block);
        $layout = Bootstrap::getObjectManager()->get(LayoutInterface::class);
        self::assertInstanceOf(LayoutInterface::class, $layout);
        $block->setLayout($layout);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"tag_list_view_model"');

        $block->render($this->element(['value' => 'A']));
    }

    /**
     * How many elements a query finds
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @return int
     */
    private function matchCount(\DOMXPath $xpath, string $query): int
    {
        $nodes = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);

        return $nodes->length;
    }

    /**
     * A consumer's virtual type of the tag list with its own settings, which inherits the block type's data items
     *
     * @param array<string,string> $tagListConfig
     * @return string
     */
    private function tagListType(array $tagListConfig): string
    {
        $name = 'HryvinskyiConfigurationFieldsTestTagList' . hash('sha256', (string) json_encode($tagListConfig));
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);
        $objectManager->configure([
            $name => ['type' => TagList::class, 'arguments' => ['tagListConfig' => $tagListConfig]],
        ]);

        return $name;
    }
}
