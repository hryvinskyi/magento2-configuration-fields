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
use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\JsonCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\SeparatorCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\SerializedCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\CodecPool;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\SettingsFactoryFake;
use Hryvinskyi\ConfigurationFields\ViewModel\TagListField;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\Serialize\Serializer\Serialize;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * The tag-list field's data: decoded values per save type, the value its hidden input posts, a stored value that
 * cannot be read kept as it is, its settings and wording, its disabled state and the mount of its script
 */
class TagListFieldTest extends FormElementTestCase
{
    /**
     * @param array<string,mixed> $tagListConfig
     * @param string $stored
     * @param list<string> $values
     * @param string $posted
     * @return void
     */
    #[DataProvider('storedValues')]
    public function testDecodesTheStoredValueAndPostsItJoinedBySeparator(
        array $tagListConfig,
        string $stored,
        array $values,
        string $posted
    ): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $config = $this->viewModel($logger)
            ->config($this->element(['html_id' => 'f', 'value' => $stored]), $tagListConfig);

        self::assertSame($values, $config['values']);
        self::assertSame($posted, $config['value']);
        self::assertFalse($config['unread']);
        self::assertSame($values, $this->componentConfig($config['mount']['init'])['values']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, list<string>, string}>
     */
    public static function storedValues(): array
    {
        return [
            'json' => [['save_type' => 'json'], '["A","Ä","0"]', ['A', 'Ä', '0'], "A\nÄ\n0"],
            'serialized' => [
                ['save_type' => 'serialized', 'separator' => ','],
                'a:2:{i:0;s:1:"A";i:1;s:3:"b c";}',
                ['A', 'b c'],
                'A,b c',
            ],
            'separator' => [[], " A \n\nB\n", ['A', 'B'], "A\nB"],
            'json list of numbers' => [['save_type' => 'json'], '[8080,8443]', ['8080', '8443'], "8080\n8443"],
            'empty' => [['save_type' => 'json'], '', [], ''],
            'blank' => [['save_type' => 'json'], " \n ", [], ''],
            'empty json list' => [['save_type' => 'json'], '[]', [], ''],
            'empty serialized list' => [['save_type' => 'serialized'], 'a:0:{}', [], ''],
        ];
    }

    /**
     * @param array<string,mixed> $tagListConfig
     * @param string $stored
     * @param string $saveType
     * @return void
     */
    #[DataProvider('unreadableValues')]
    public function testAStoredValueThatCannotBeReadIsKeptAsItIsAndFlagged(
        array $tagListConfig,
        string $stored,
        string $saveType
    ): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::logicalAnd(self::stringContains('sec/grp/fld'), self::stringContains('"' . $saveType . '"'))
        );

        $config = $this->viewModel($logger)->config(
            $this->element([
                'html_id' => 'f',
                'value' => $stored,
                'field_config' => ['path' => 'sec/grp', 'id' => 'fld'],
            ]),
            $tagListConfig
        );

        self::assertSame([], $config['values']);
        self::assertSame($stored, $config['value']);
        self::assertTrue($config['unread']);
        self::assertSame(
            'The stored value could not be read as a list. It is kept as it is until you add or remove a value here.',
            $config['unreadNotice']
        );
        self::assertSame([], $this->componentConfig($config['mount']['init'])['values']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function unreadableValues(): array
    {
        return [
            'values joined by a newline in a json field' => [['save_type' => 'json'], "A\nB", 'json'],
            'corrupted json' => [['save_type' => 'json'], '["A",', 'json'],
            'json list without a value' => [['save_type' => 'json'], '[true,null]', 'json'],
            'json in a serialized field' => [['save_type' => 'serialized'], '["A"]', 'serialized'],
            'serialized list without a value' => [['save_type' => 'serialized'], 'a:1:{i:0;b:1;}', 'serialized'],
        ];
    }

    public function testAStoredValueThatIsNotUtf8IsPrintableAndSerializable(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'f', 'value' => "a\xb1"]), []);

        self::assertTrue(mb_check_encoding($config['value'], 'UTF-8'));
        self::assertCount(1, $config['values']);
        self::assertTrue(mb_check_encoding($config['values'][0], 'UTF-8'));
        self::assertStringStartsWith('a', $config['values'][0]);
        self::assertJson($config['mount']['initJson']);
    }

    public function testAnUnknownSaveTypeIsLoggedOnceWithThePathAndReadAsSeparated(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::logicalAnd(self::stringContains('sec/grp/fld'), self::stringContains('"JSON"'))
        );

        $config = $this->viewModel($logger)->config(
            $this->element([
                'html_id' => 'f',
                'value' => "A\nB",
                'field_config' => ['path' => 'sec/grp', 'id' => 'fld'],
            ]),
            ['save_type' => 'JSON']
        );

        self::assertSame(['A', 'B'], $config['values']);
        self::assertSame("A\nB", $config['value']);
        self::assertFalse($config['unread']);
    }

    public function testCarriesTheSettingsAndTranslatedWording(): void
    {
        $config = $this->viewModel()->config(
            $this->element(['html_id' => 'f', 'name' => 'groups[g][fields][f][value]', 'label' => 'Postcodes']),
            ['placeholder' => 'Add one', 'validation' => '^[A-Z]+$', 'uppercase' => false, 'separator' => '\t']
        );
        $component = $this->componentConfig($config['mount']['init']);

        self::assertSame('f', $config['inputId']);
        self::assertSame('groups[g][fields][f][value]', $config['inputName']);
        self::assertSame('f_inherit', $config['inheritId']);
        self::assertSame('Add one', $config['placeholder']);
        self::assertSame('Postcodes', $config['inputLabel']);
        self::assertSame('Search values…', $config['searchPlaceholder']);
        self::assertSame('Search values', $config['searchLabel']);
        self::assertSame(
            [
                'inputId' => 'f',
                'inheritId' => 'f_inherit',
                'disabled' => false,
                'values' => [],
                'separator' => "\t",
                'validation' => '^[A-Z]+$',
                'validationMessage' => 'This value does not match the format this field accepts.',
                'uppercase' => false,
                'wording' => [
                    'remove' => 'Remove %1',
                    'duplicate' => 'This value already exists.',
                    'searchPlaceholder' => 'Search values…',
                    'searchLabel' => 'Search values',
                ],
            ],
            $component
        );
    }

    public function testTheDefaultPatternIsExplainedByTheCharactersItAccepts(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'f']), []);

        self::assertSame(
            'Use letters, digits and . ! # $ % & \' * + / = ? ^ _ ` " { | } ~ - only, without spaces.',
            $this->componentConfig($config['mount']['init'])['validationMessage']
        );
    }

    public function testAConfiguredValidationMessageReplacesTheDefault(): void
    {
        $config = $this->viewModel()->config(
            $this->element(['html_id' => 'f']),
            ['validation_message' => 'Postcodes only']
        );

        self::assertSame('Postcodes only', $this->componentConfig($config['mount']['init'])['validationMessage']);
    }

    /**
     * @param array<string,mixed> $data
     * @param bool $disabled
     * @return void
     */
    #[DataProvider('disabledStates')]
    public function testDisabledIsTheElementsDisabledFlagNotWhetherItCouldInherit(array $data, bool $disabled): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'f'] + $data), []);

        self::assertSame($disabled, $config['disabled']);
        self::assertSame($disabled, $this->componentConfig($config['mount']['init'])['disabled']);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function disabledStates(): array
    {
        return [
            'could inherit, own value' => [
                ['can_use_default_value' => true, 'can_use_website_value' => true, 'can_restore_to_default' => true],
                false,
            ],
            'inherited' => [['can_use_default_value' => true, 'disabled' => true], true],
            'locked' => [['disabled' => 'disabled'], true],
        ];
    }

    public function testMountsTheTagListScriptOnTheRoot(): void
    {
        $element = $this->element(['html_id' => 'f"<x>']);
        $config = $this->viewModel()->config($element, []);
        $rootId = (new ElementIdentity())->rootId($element);

        self::assertSame($rootId, $config['mount']['rootId']);
        self::assertSame(['#' . $rootId], array_keys($config['mount']['init']));
        self::assertSame([TagListField::COMPONENT], array_keys($config['mount']['init']['#' . $rootId]));
    }

    /**
     * The view model with the module's three storage formats
     *
     * @param LoggerInterface|null $logger
     * @return TagListField
     */
    private function viewModel(?LoggerInterface $logger = null): TagListField
    {
        $normalizer = new ValueListNormalizer();
        $identity = new ElementIdentity();

        return new TagListField(
            $identity,
            new StoredValue(),
            new WidgetMount($identity, new JsonHexTag()),
            new SettingsReader(new SettingsFactoryFake()),
            new CodecPool([
                'json' => new JsonCodec(new Json(), $normalizer),
                'serialized' => new SerializedCodec(new Serialize(), $normalizer),
                'separator' => new SeparatorCodec($normalizer),
            ]),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * The config the script receives
     *
     * @param array<non-empty-string, array<string, array<string, mixed>>> $init
     * @return array<string, mixed>
     */
    private function componentConfig(array $init): array
    {
        $byComponent = array_values($init)[0] ?? [];

        return $byComponent[TagListField::COMPONENT] ?? [];
    }
}
