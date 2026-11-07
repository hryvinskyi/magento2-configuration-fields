<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\ViewModel;

use Hryvinskyi\ConfigurationFields\Api\CategoryOptionSourceInterface;
use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\ScopeStore;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Hryvinskyi\ConfigurationFields\ViewModel\UiSelectField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The category select's data: the stored ids as stored, the categories of the store the field's scope shows, whether
 * it takes several ids, the disabled state, the names the script and the template share, and the notice for an
 * element type it cannot draw
 */
class UiSelectFieldTest extends FormElementTestCase
{
    private const CATEGORIES = [
        ['id' => '2', 'parentId' => '1', 'label' => 'Default Category'],
        ['id' => '3', 'parentId' => '2', 'label' => 'Gear'],
    ];

    /**
     * @var list<int>
     */
    private array $requestedStores = [];

    public function testAMultiselectCarriesItsStoredIdsTheCategoriesAndTheMount(): void
    {
        $element = $this->element([
            'html_id' => 'cats',
            'name' => 'groups[g][fields][cats][value]',
            'type' => 'select', 'ext_type' => 'multiple',
            'value' => '2,3',
        ]);
        $rootId = (new ElementIdentity())->rootId($element);
        $componentName = str_replace('-', '_', $rootId) . '_select';
        $config = $this->viewModel()->config($element);

        self::assertSame($componentName, $config['componentName']);
        self::assertSame('cats', $config['inputId']);
        self::assertSame('groups[g][fields][cats][value]', $config['inputName']);
        self::assertSame('2,3', $config['value']);
        self::assertFalse($config['disabled']);
        self::assertSame(
            [
                '#' . $rootId => [
                    UiSelectField::COMPONENT => [
                        'componentName' => $componentName,
                        'inputId' => 'cats',
                        'inheritId' => 'cats_inherit',
                        'disabled' => false,
                        'multiple' => true,
                        'value' => '2,3',
                        'categories' => self::CATEGORIES,
                    ],
                ],
            ],
            $config['mount']['init']
        );
    }

    public function testASelectTakesOneId(): void
    {
        $config = $this->viewModel()->config(
            $this->element(['html_id' => 'cat', 'type' => 'select', 'ext_type' => 'combobox', 'value' => '3'])
        );

        self::assertSame('3', $config['value']);
        self::assertFalse($this->componentConfig($config)['multiple']);
    }

    public function testAnEmptyFieldStaysEmpty(): void
    {
        $config = $this->viewModel()->config(
            $this->element(['html_id' => 'cat', 'type' => 'select', 'ext_type' => 'multiple'])
        );

        self::assertSame('', $config['value']);
        self::assertSame('', $this->componentConfig($config)['value']);
    }

    public function testAStoredListIsJoinedByCommasAsTheFieldPostsIt(): void
    {
        $config = $this->viewModel()->config(
            $this->element(
                ['html_id' => 'cats', 'type' => 'select', 'ext_type' => 'multiple', 'value' => ['2', 3, ['nested']]]
            )
        );

        self::assertSame('2,3', $config['value']);
    }

    public function testTheCategoriesAreLabelledInTheStoreTheFieldsScopeShows(): void
    {
        $this->viewModel()->config(
            $this->element(['html_id' => 'cat', 'type' => 'select', 'scope' => 'stores', 'scope_id' => '4'])
        );
        $this->viewModel()->config($this->element(['html_id' => 'cat', 'type' => 'select', 'scope' => 'default']));

        self::assertSame([4, 0], $this->requestedStores);
    }

    public function testDisabledIsTheElementsDisabledFlag(): void
    {
        $config = $this->viewModel()->config(
            $this->element(
                ['html_id' => 'cat', 'type' => 'select', 'disabled' => true, 'can_use_default_value' => true]
            )
        );

        self::assertTrue($config['disabled']);
        self::assertTrue($this->componentConfig($config)['disabled']);
    }

    public function testTheComponentNameIsSafeInABindingWhateverTheHtmlId(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => "a'b\"<c> d", 'type' => 'select']));

        self::assertMatchesRegularExpression('/^hcf_[a-f0-9]+_select$/', $config['componentName']);
        self::assertSame("a'b\"<c> d", $config['inputId']);
    }

    public function testAStoredValueThatIsNotUtf8IsPrintableAndSerializable(): void
    {
        $config = $this->viewModel()->config(
            $this->element(['html_id' => 'cat', 'type' => 'select', 'value' => "3\xb1"])
        );

        self::assertTrue(mb_check_encoding($config['value'], 'UTF-8'));
        self::assertJson($config['mount']['initJson']);
    }

    public function testOnlyMagentosSelectAndMultiselectElementsAreDrawn(): void
    {
        $multiselect = ['type' => 'select', 'ext_type' => 'multiple'];

        self::assertNull($this->viewModel()->unsupportedNotice($this->element(['type' => 'select'])));
        self::assertNull($this->viewModel()->unsupportedNotice($this->element($multiselect)));
        self::assertNull(
            $this->viewModel()->unsupportedNotice($this->element(['type' => 'select', 'ext_type' => 'combobox']))
        );
        self::assertSame(
            'The field type "<b>text</b>" cannot be drawn as a category select. Use "select" or "multiselect".',
            $this->viewModel()->unsupportedNotice($this->element(['type' => '<b>text</b>']))
        );
    }

    public function testAnUnsupportedTypeHasNoWidgetAndReadsNoCategories(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->viewModel()->config($this->element(['html_id' => 'cat', 'type' => 'textarea']));
        } finally {
            self::assertSame([], $this->requestedStores);
        }
    }

    /**
     * A form element whose type is the data's `type`, as Magento's element classes set it
     *
     * @param array<string,mixed> $data
     * @param string $htmlIdPrefix
     * @return AbstractElement
     */
    protected function element(array $data, string $htmlIdPrefix = ''): AbstractElement
    {
        $element = parent::element($data, $htmlIdPrefix);
        $type = $data['type'] ?? null;
        if (is_string($type)) {
            $element->setType($type);
        }

        return $element;
    }

    /**
     * The config the script receives
     *
     * @param array{
     *     mount: array{
     *         rootId: non-empty-string,
     *         init: array<non-empty-string, array<string, array<string, mixed>>>,
     *         initJson: string
     *     }
     * } $config
     * @return array<string, mixed>
     */
    private function componentConfig(array $config): array
    {
        $byComponent = $config['mount']['init']['#' . $config['mount']['rootId']] ?? [];

        return $byComponent[UiSelectField::COMPONENT] ?? [];
    }

    /**
     * @return UiSelectField
     */
    private function viewModel(): UiSelectField
    {
        $identity = new ElementIdentity();
        $test = $this;
        $source = new class ($test) implements CategoryOptionSourceInterface {
            /**
             * @param UiSelectFieldTest $test
             */
            public function __construct(private readonly UiSelectFieldTest $test)
            {
            }

            /**
             * @inheritDoc
             */
            public function categories(int $storeId): array
            {
                $this->test->recordStore($storeId);

                return [
                    ['id' => '2', 'parentId' => '1', 'label' => 'Default Category'],
                    ['id' => '3', 'parentId' => '2', 'label' => 'Gear'],
                ];
            }
        };

        return new UiSelectField(
            $identity,
            new StoredValue(),
            new WidgetMount($identity, new JsonHexTag()),
            new ScopeStore($this->createMock(StoreManagerInterface::class)),
            $source
        );
    }

    /**
     * Records a store the categories were read for
     *
     * @param int $storeId
     * @return void
     */
    public function recordStore(int $storeId): void
    {
        $this->requestedStores[] = $storeId;
    }
}
