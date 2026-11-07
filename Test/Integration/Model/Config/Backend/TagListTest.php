<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecInterface;
use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Hryvinskyi\ConfigurationFields\Model\Config\Backend\TagList;
use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\JsonCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\CodecPool;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The backend model and the codec pool as the container wires them: what a config save stores per save type, and
 * that the field reads it back
 */
class TagListTest extends TestCase
{
    private const POSTED = " A \n\nÄ\na/b\n0\n";

    /**
     * @param array<string, mixed> $tagListConfig
     * @param string $posted
     * @param string $stored
     * @param list<string> $values
     * @return void
     */
    #[DataProvider('saveTypes')]
    public function testStoresThePostedValuesInTheFormatOfTheSaveTypeAndReadsThemBack(
        array $tagListConfig,
        string $posted,
        string $stored,
        array $values
    ): void {
        $model = $this->backendModel($tagListConfig);
        $model->setValue($posted);

        $model->beforeSave();

        self::assertSame($stored, $model->getValue());
        $objectManager = Bootstrap::getObjectManager();
        $reader = $objectManager->get(SettingsReader::class);
        $pool = $objectManager->get(TagListCodecPoolInterface::class);
        self::assertInstanceOf(SettingsReader::class, $reader);
        self::assertInstanceOf(TagListCodecPoolInterface::class, $pool);
        $settings = $reader->read($tagListConfig);
        self::assertSame(
            $values,
            $pool->forSaveType($settings->saveType())->decode($stored, $settings->separator())
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, list<string>}>
     */
    public static function saveTypes(): array
    {
        $values = ['A', 'Ä', 'a/b', '0'];

        return [
            'json' => [['save_type' => 'json'], self::POSTED, '["A","Ä","a\/b","0"]', $values],
            'serialized' => [
                ['save_type' => 'serialized'],
                self::POSTED,
                'a:4:{i:0;s:1:"A";i:1;s:2:"Ä";i:2;s:3:"a/b";i:3;s:1:"0";}',
                $values,
            ],
            'separator by default' => [[], self::POSTED, "A\nÄ\na/b\n0", $values],
            'separator spelled in di.xml' => [
                ['save_type' => 'separator', 'separator' => '\n'],
                self::POSTED,
                "A\nÄ\na/b\n0",
                $values,
            ],
            'comma separator' => [['separator' => ','], ' A,,Ä , a/b,0', 'A,Ä,a/b,0', $values],
            'json with a comma separator' => [
                ['save_type' => 'json', 'separator' => ','],
                'A, B',
                '["A","B"]',
                ['A', 'B'],
            ],
        ];
    }

    public function testTranslatableSettingsDoNotStopTheSave(): void
    {
        $model = $this->backendModel([
            'placeholder' => new Phrase('Add a route'),
            'validation_message' => new Phrase('Routes only'),
            'save_type' => 'json',
        ]);
        $model->setValue("checkout\ncart");

        $model->beforeSave();

        self::assertSame('["checkout","cart"]', $model->getValue());
    }

    /**
     * @param string $saveType
     * @return void
     */
    #[DataProvider('everySaveType')]
    public function testAnEmptyPostStoresTheEmptyEncoding(string $saveType): void
    {
        $model = $this->backendModel(['save_type' => $saveType]);
        $model->setValue('');

        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    public function testAnUnknownSaveTypeRefusesTheSave(): void
    {
        $model = $this->backendModel(['save_type' => 'serialize']);
        $model->setValue("A\nB");

        try {
            $model->beforeSave();
            self::fail('A save type no format is registered under must refuse the save.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('"serialize" is not registered', $exception->getMessage());
            self::assertStringContainsString('json, serialized, separator', $exception->getMessage());
        }
        self::assertSame("A\nB", $model->getValue());
    }

    /**
     * @param string $saveType
     * @return void
     */
    #[DataProvider('everySaveType')]
    public function testInvalidUtf8RefusesTheSave(string $saveType): void
    {
        $model = $this->backendModel(['save_type' => $saveType]);
        $model->setValue("A\n\xB1\x31");

        try {
            $model->beforeSave();
            self::fail('A posted value that is not UTF-8 must refuse the save.');
        } catch (LocalizedException $exception) {
            self::assertSame(
                'A tag list value is not valid UTF-8 text, so the list cannot be saved.',
                $exception->getMessage()
            );
        }
        self::assertSame("A\n\xB1\x31", $model->getValue());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everySaveType(): array
    {
        return [
            'json' => ['json'],
            'serialized' => ['serialized'],
            'separator' => ['separator'],
        ];
    }

    public function testAValueStoredInAnotherFormatAndPostedBackUnchangedIsReEncoded(): void
    {
        $model = $this->backendModel(['save_type' => 'json']);
        $model->setValue("A\nB");

        $model->beforeSave();

        self::assertSame('["A","B"]', $model->getValue());
    }

    public function testAPostedValueTheSeparatorFormatCannotReadRefusesTheSave(): void
    {
        $unreadable = new class implements TagListCodecInterface {
            /**
             * @inheritDoc
             */
            public function encode(array $values, string $separator): string
            {
                return implode($separator, $values);
            }

            /**
             * @inheritDoc
             */
            public function decode(string $stored, string $separator): ?array
            {
                return null;
            }
        };
        $objectManager = Bootstrap::getObjectManager();
        $model = $objectManager->create(TagList::class, [
            'tagListConfig' => ['save_type' => 'json'],
            'codecPool' => $objectManager->create(CodecPool::class, [
                'codecs' => [
                    'json' => $objectManager->get(JsonCodec::class),
                    'separator' => $unreadable,
                ],
            ]),
        ]);
        self::assertInstanceOf(TagList::class, $model);
        $model->setValue("A\nB");

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be read as values joined by the separator');

        $model->beforeSave();
    }

    /**
     * The backend model built by the container, as a consumer's virtualType configures it, for a path no field is
     * declared under, with a pattern that accepts every value unless the test names one: these tests are about the
     * storage formats, and the pattern check has tests of its own
     *
     * @param array<string, mixed> $tagListConfig
     * @return TagList
     */
    private function backendModel(array $tagListConfig): TagList
    {
        $model = Bootstrap::getObjectManager()->create(
            TagList::class,
            ['tagListConfig' => $tagListConfig + ['validation' => '^.+$']]
        );
        self::assertInstanceOf(TagList::class, $model);
        $model->setPath('hryvinskyi_configuration_fields_test/tag_list/values');

        return $model;
    }
}
