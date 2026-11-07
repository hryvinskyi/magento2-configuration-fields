<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\FieldBlockConfigInterface;
use Hryvinskyi\ConfigurationFields\Model\TagList\FrontendModelSettingsSource;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\SettingsFactoryFake;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Config\Model\Config\Structure\Element\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A tag-list field's settings: the field found by its path, its frontend model's `tagListConfig` read as the settings
 */
class FrontendModelSettingsSourceTest extends TestCase
{
    private const PATH = 'section/group/field';
    private const VIRTUAL_TYPE = 'Consumer\Block\IpListField';

    /**
     * @var Structure&MockObject
     */
    private Structure $structure;

    /**
     * Frontend models the field block source was asked about
     *
     * @var list<string>
     */
    private array $asked = [];

    /**
     * Frontend model => its `tagListConfig`; a model not listed is not a tag list
     *
     * @var array<string, array<string, string|bool>>
     */
    private array $blocks = [];

    protected function setUp(): void
    {
        $this->structure = $this->createMock(Structure::class);
    }

    public function testReadsTheSettingsTheFrontendModelConfigures(): void
    {
        $this->fieldWithFrontendModel(self::VIRTUAL_TYPE);
        $this->blocks[self::VIRTUAL_TYPE] = ['validation' => '^[0-9.:]+$', 'uppercase' => false];

        $settings = $this->source()->forPath(self::PATH);

        self::assertNotNull($settings);
        self::assertSame('^[0-9.:]+$', $settings->validation());
        self::assertFalse($settings->uppercase());
        self::assertSame([self::VIRTUAL_TYPE], $this->asked);
    }

    public function testAsksForTheFrontendModelWithoutItsLeadingBackslash(): void
    {
        $this->fieldWithFrontendModel('\\' . self::VIRTUAL_TYPE);
        $this->blocks[self::VIRTUAL_TYPE] = [];

        self::assertNotNull($this->source()->forPath(self::PATH));
        self::assertSame([self::VIRTUAL_TYPE], $this->asked);
    }

    public function testAFrontendModelThatIsNotATagListGivesNull(): void
    {
        $this->fieldWithFrontendModel('Consumer\Block\CronField');

        self::assertNull($this->source()->forPath(self::PATH));
    }

    public function testAFieldWithoutAFrontendModelAsksNothing(): void
    {
        $this->fieldWithFrontendModel('');

        self::assertNull($this->source()->forPath(self::PATH));
        self::assertSame([], $this->asked);
    }

    public function testAPathThatNamesNoFieldGivesNull(): void
    {
        $this->structure->method('getElementByConfigPath')->willReturn($this->createMock(Group::class));

        self::assertNull($this->source()->forPath(self::PATH));
        self::assertSame([], $this->asked);
    }

    /**
     * @param string $frontendModel
     * @return void
     */
    private function fieldWithFrontendModel(string $frontendModel): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getFrontendModel')->willReturn($frontendModel);
        $this->structure->method('getElementByConfigPath')->with(self::PATH)->willReturn($field);
    }

    /**
     * @return FrontendModelSettingsSource
     */
    private function source(): FrontendModelSettingsSource
    {
        $fieldBlocks = $this->createMock(FieldBlockConfigInterface::class);
        $fieldBlocks->method('tagListConfig')->willReturnCallback(function (string $frontendModel): ?array {
            $this->asked[] = $frontendModel;

            return $this->blocks[$frontendModel] ?? null;
        });

        return new FrontendModelSettingsSource(
            $this->structure,
            $fieldBlocks,
            new SettingsReader(new SettingsFactoryFake())
        );
    }
}
