<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList\FieldBlock;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CronEditor;
use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList as TagListField;
use Hryvinskyi\ConfigurationFields\Model\TagList\FieldBlock\DeclaredFieldBlockConfig;
use Magento\Framework\ObjectManager\Config\Config;
use Magento\Framework\ObjectManager\Config\ConfigFactory;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use PHPUnit\Framework\TestCase;

/**
 * A field block's `tagListConfig` read from the global and admin-area declarations, merged by a container
 * configuration of its own as Magento merges them, and only those two areas' declarations, read once
 */
class DeclaredFieldBlockConfigTest extends TestCase
{
    private const GLOBAL_DECLARATIONS = [
        'Consumer\Block\BaseListField' => [
            'type' => TagListField::class,
            'arguments' => ['tagListConfig' => ['validation' => '^[a-z]+$', 'uppercase' => false]],
        ],
        'preferences' => ['Consumer\Api\SomethingInterface' => 'Consumer\Model\Something'],
    ];

    private const ADMIN_DECLARATIONS = [
        'Consumer\Block\IpListField' => [
            'type' => TagListField::class,
            'arguments' => [
                'tagListConfig' => [
                    'validation' => '^[0-9a-f:.]+$',
                    'validation_message' => 'Enter a valid IPv4 or IPv6 address.',
                    'uppercase' => false,
                ],
            ],
        ],
        'Consumer\Block\ChildListField' => [
            'type' => 'Consumer\Block\BaseListField',
            'arguments' => ['tagListConfig' => ['uppercase' => true, 'separator' => ',']],
        ],
        'Consumer\Block\CronField' => ['type' => CronEditor::class, 'arguments' => []],
        'Consumer\Block\ObjectItemField' => [
            'type' => TagListField::class,
            'arguments' => ['tagListConfig' => ['placeholder' => ['instance' => 'Consumer\Model\Placeholder']]],
        ],
    ];

    private const STOREFRONT_DECLARATIONS = [
        'Consumer\Block\IpListField' => [
            'type' => TagListField::class,
            'arguments' => ['tagListConfig' => ['validation' => '^storefront$']],
        ],
    ];

    /**
     * Area => how many times its declarations were read
     *
     * @var array<string, int>
     */
    private array $reads = [];

    public function testAVirtualTypeDeclaredForTheAdminAreaGivesItsArgument(): void
    {
        self::assertSame(
            [
                'validation' => '^[0-9a-f:.]+$',
                'validation_message' => 'Enter a valid IPv4 or IPv6 address.',
                'uppercase' => false,
            ],
            $this->config()->tagListConfig('Consumer\Block\IpListField')
        );
    }

    public function testAVirtualTypeOfAGlobalVirtualTypeMergesOverItsArgument(): void
    {
        self::assertSame(
            ['validation' => '^[a-z]+$', 'uppercase' => true, 'separator' => ','],
            $this->config()->tagListConfig('Consumer\Block\ChildListField')
        );
    }

    public function testTheTagListBlockItselfGivesAnEmptyArgument(): void
    {
        self::assertSame([], $this->config()->tagListConfig(TagListField::class));
    }

    public function testAnItemThatIsNotAValueIsLeftAsDeclared(): void
    {
        self::assertSame(
            ['placeholder' => ['instance' => 'Consumer\Model\Placeholder']],
            $this->config()->tagListConfig('Consumer\Block\ObjectItemField')
        );
    }

    public function testAFrontendModelOfAnotherKindGivesNull(): void
    {
        $config = $this->config();

        self::assertNull($config->tagListConfig('Consumer\Block\CronField'));
        self::assertNull($config->tagListConfig(CronEditor::class));
    }

    public function testANameNoDeclarationKnowsGivesNull(): void
    {
        self::assertNull($this->config()->tagListConfig('Consumer\Block\UnknownField'));
    }

    public function testReadsTheGlobalAndAdminDeclarationsOnceAndNoOtherAreas(): void
    {
        $config = $this->config();

        $config->tagListConfig('Consumer\Block\IpListField');
        $config->tagListConfig('Consumer\Block\ChildListField');
        $config->tagListConfig('Consumer\Block\UnknownField');

        self::assertSame(['global' => 1, 'adminhtml' => 1], $this->reads);
    }

    /**
     * @return DeclaredFieldBlockConfig
     */
    private function config(): DeclaredFieldBlockConfig
    {
        $declarations = $this->createMock(ConfigLoaderInterface::class);
        $declarations->method('load')->willReturnCallback(function (string $area): array {
            $this->reads[$area] = ($this->reads[$area] ?? 0) + 1;

            return match ($area) {
                'global' => self::GLOBAL_DECLARATIONS,
                'adminhtml' => self::ADMIN_DECLARATIONS,
                'frontend' => self::STOREFRONT_DECLARATIONS,
                default => [],
            };
        });
        $configFactory = $this->createMock(ConfigFactory::class);
        $configFactory->method('create')->willReturnCallback(static fn (): Config => new Config());

        return new DeclaredFieldBlockConfig($declarations, $configFactory);
    }
}
