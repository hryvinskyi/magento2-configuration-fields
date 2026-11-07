<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Category;

use Hryvinskyi\ConfigurationFields\Api\CategoryOptionSourceInterface;
use Hryvinskyi\ConfigurationFields\Model\Category\CachedCategoryOptionSource;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Cache\Type\Block;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Authorization\RoleLocatorInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The categories are read once per store and admin role, kept under this module's own cache id with the tags that
 * clear them when a category or the block cache is cleaned, and read again when the kept entry is not a category list
 */
class CachedCategoryOptionSourceTest extends TestCase
{
    private const ROWS = [
        ['id' => '2', 'parentId' => '1', 'label' => 'Default Category'],
        ['id' => '3', 'parentId' => '2', 'label' => 'Gear & "More"'],
    ];

    /**
     * @var array<string, array{data: string, tags: list<string>}>
     */
    private array $entries = [];

    /**
     * @var list<int>
     */
    private array $reads = [];

    public function testAMissReadsTheCategoriesAndKeepsThemUnderTheModulesOwnIdWithTheCategoryTags(): void
    {
        $rows = $this->source('7')->categories(3);

        self::assertSame(self::ROWS, $rows);
        self::assertSame([3], $this->reads);
        self::assertSame([CachedCategoryOptionSource::CACHE_ID_PREFIX . '3_7'], array_keys($this->entries));
        self::assertStringStartsWith('HRYVINSKYI_CONFIGURATION_FIELDS_', CachedCategoryOptionSource::CACHE_ID_PREFIX);
        self::assertSame(
            [Category::CACHE_TAG, Block::CACHE_TAG],
            $this->entries[CachedCategoryOptionSource::CACHE_ID_PREFIX . '3_7']['tags']
        );
    }

    public function testAHitIsServedWithoutReadingTheCategories(): void
    {
        $this->source('7')->categories(3);
        $this->reads = [];

        self::assertSame(self::ROWS, $this->source('7')->categories(3));
        self::assertSame([], $this->reads);
    }

    public function testEachStoreAndEachRoleHasItsOwnEntry(): void
    {
        $this->source('7')->categories(3);
        $this->source('8')->categories(3);
        $this->source('7')->categories(1);
        $this->source(null)->categories(1);

        self::assertSame([3, 3, 1, 1], $this->reads);
        self::assertSame(
            [
                CachedCategoryOptionSource::CACHE_ID_PREFIX . '3_7',
                CachedCategoryOptionSource::CACHE_ID_PREFIX . '3_8',
                CachedCategoryOptionSource::CACHE_ID_PREFIX . '1_7',
                CachedCategoryOptionSource::CACHE_ID_PREFIX . '1',
            ],
            array_keys($this->entries)
        );
    }

    /**
     * @param string $entry
     * @return void
     */
    #[DataProvider('unreadableEntries')]
    public function testAnEntryThatIsNotACategoryListIsReadAgainAndReplaced(string $entry): void
    {
        $this->entries[CachedCategoryOptionSource::CACHE_ID_PREFIX . '3'] = ['data' => $entry, 'tags' => []];

        self::assertSame(self::ROWS, $this->source(null)->categories(3));
        self::assertSame([3], $this->reads);
        self::assertSame(
            self::ROWS,
            json_decode($this->entries[CachedCategoryOptionSource::CACHE_ID_PREFIX . '3']['data'], true)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableEntries(): array
    {
        return [
            'not json' => ['{'],
            'not a list' => ['{"a":{"id":"2","parentId":"1","label":"x"}}'],
            'a row missing its label' => ['[{"id":"2","parentId":"1"}]'],
            'a row with a numeric id' => ['[{"id":2,"parentId":"1","label":"x"}]'],
            'a scalar' => ['"rows"'],
        ];
    }

    public function testAnEmptyCatalogIsKeptAndServedEmpty(): void
    {
        $this->entries[CachedCategoryOptionSource::CACHE_ID_PREFIX . '3'] = ['data' => '[]', 'tags' => []];

        self::assertSame([], $this->source(null)->categories(3));
        self::assertSame([], $this->reads);
    }

    /**
     * The cached source over a recording inner source and an in-memory cache, for an admin of the given role
     *
     * @param string|null $role
     * @return CachedCategoryOptionSource
     */
    private function source(?string $role): CachedCategoryOptionSource
    {
        $roleLocator = $this->createMock(RoleLocatorInterface::class);
        $roleLocator->method('getAclRoleId')->willReturn($role);

        return new CachedCategoryOptionSource($this->innerSource(), $this->cache(), new Json(), $roleLocator);
    }

    /**
     * @return CategoryOptionSourceInterface
     */
    private function innerSource(): CategoryOptionSourceInterface
    {
        $test = $this;

        return new class ($test) implements CategoryOptionSourceInterface {
            /**
             * @param CachedCategoryOptionSourceTest $test
             */
            public function __construct(private readonly CachedCategoryOptionSourceTest $test)
            {
            }

            /**
             * @inheritDoc
             */
            public function categories(int $storeId): array
            {
                $this->test->recordRead($storeId);

                return [
                    ['id' => '2', 'parentId' => '1', 'label' => 'Default Category'],
                    ['id' => '3', 'parentId' => '2', 'label' => 'Gear & "More"'],
                ];
            }
        };
    }

    /**
     * @return CacheInterface
     */
    private function cache(): CacheInterface
    {
        $test = $this;

        return new class ($test) implements CacheInterface {
            /**
             * @param CachedCategoryOptionSourceTest $test
             */
            public function __construct(private readonly CachedCategoryOptionSourceTest $test)
            {
            }

            /**
             * @inheritDoc
             */
            public function getFrontend(): FrontendInterface
            {
                throw new \LogicException('Not used by the source.');
            }

            /**
             * The kept entry; an empty string for a miss, which the source reads as Magento's `false`
             *
             * @param string $identifier
             * @return string
             */
            public function load($identifier)
            {
                return $this->test->entry($identifier);
            }

            /**
             * @param string $data
             * @param string $identifier
             * @param array<mixed> $tags
             * @param int|null $lifeTime
             * @return bool
             */
            public function save($data, $identifier, $tags = [], $lifeTime = null)
            {
                $this->test->keep($data, $identifier, $tags);

                return true;
            }

            /**
             * @inheritDoc
             */
            public function remove($identifier)
            {
                throw new \LogicException('Not used by the source.');
            }

            /**
             * @param array<mixed> $tags
             * @return bool
             */
            public function clean($tags = [])
            {
                throw new \LogicException('Not used by the source.');
            }
        };
    }

    /**
     * Records a read of the inner source
     *
     * @param int $storeId
     * @return void
     */
    public function recordRead(int $storeId): void
    {
        $this->reads[] = $storeId;
    }

    /**
     * The kept entry, or an empty string for a miss
     *
     * @param string $identifier
     * @return string
     */
    public function entry(string $identifier): string
    {
        return $this->entries[$identifier]['data'] ?? '';
    }

    /**
     * Keeps an entry
     *
     * @param mixed $data
     * @param mixed $identifier
     * @param mixed $tags
     * @return void
     */
    public function keep(mixed $data, mixed $identifier, mixed $tags): void
    {
        self::assertIsString($data);
        self::assertIsString($identifier);
        self::assertIsArray($tags);
        $this->entries[$identifier] = ['data' => $data, 'tags' => array_values(array_filter($tags, 'is_string'))];
    }
}
