<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Category;

use Hryvinskyi\ConfigurationFields\Model\Category\CategoryOptionSource;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

/**
 * Every category below the catalog's root, read in one collection, labelled in the given store, in catalog order
 */
class CategoryOptionSourceTest extends TestCase
{
    /**
     * @var list<array{string, list<mixed>}>
     */
    private array $calls = [];

    public function testReadsEveryCategoryBelowTheRootLabelledInTheStore(): void
    {
        $rows = $this->source([
            new DataObject(['entity_id' => '2', 'parent_id' => '1', 'name' => 'Default Category']),
            new DataObject(['entity_id' => 3, 'parent_id' => 2, 'name' => 'Gear']),
        ])->categories(4);

        self::assertSame(
            [
                ['id' => '2', 'parentId' => '1', 'label' => 'Default Category'],
                ['id' => '3', 'parentId' => '2', 'label' => 'Gear'],
            ],
            $rows
        );
        self::assertTrue($this->called('setStoreId', [4]));
        self::assertTrue($this->called('addAttributeToSelect', ['name']));
        self::assertTrue($this->called('addAttributeToFilter', ['entity_id', ['neq' => Category::TREE_ROOT_ID]]));
        self::assertTrue($this->called('setOrder', ['position', 'ASC']));
    }

    public function testACategoryWithoutANameIsListedWithAnEmptyLabel(): void
    {
        self::assertSame(
            [['id' => '5', 'parentId' => '2', 'label' => '']],
            $this->source([new DataObject(['entity_id' => '5', 'parent_id' => '2'])])->categories(0)
        );
    }

    public function testARowWithoutAnIdOrAParentIsLeftOut(): void
    {
        self::assertSame(
            [],
            $this->source([
                new DataObject(['parent_id' => '2', 'name' => 'No id']),
                new DataObject(['entity_id' => '6', 'name' => 'No parent']),
            ])->categories(0)
        );
    }

    /**
     * Whether the collection was asked this method with these leading arguments; the rest are the method's defaults
     *
     * @param string $method
     * @param list<mixed> $arguments
     * @return bool
     */
    private function called(string $method, array $arguments): bool
    {
        foreach ($this->calls as [$calledMethod, $calledArguments]) {
            if ($calledMethod === $method && array_slice($calledArguments, 0, count($arguments)) === $arguments) {
                return true;
            }
        }

        return false;
    }

    /**
     * The source over a collection that yields the given items and records how it was asked
     *
     * @param list<DataObject> $items
     * @return CategoryOptionSource
     */
    private function source(array $items): CategoryOptionSource
    {
        $collection = $this->createMock(Collection::class);
        foreach (['setStoreId', 'addAttributeToSelect', 'addAttributeToFilter', 'setOrder'] as $method) {
            $collection->method($method)->willReturnCallback(
                function (mixed ...$arguments) use ($method, $collection): Collection {
                    $this->calls[] = [$method, array_values($arguments)];

                    return $collection;
                }
            );
        }
        $collection->method('getItems')->willReturn($items);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new CategoryOptionSource($factory);
    }
}
