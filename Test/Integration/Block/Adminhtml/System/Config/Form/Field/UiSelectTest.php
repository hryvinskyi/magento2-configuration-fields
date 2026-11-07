<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\UiSelect;
use Hryvinskyi\ConfigurationFields\Model\Category\CachedCategoryOptionSource;
use Hryvinskyi\ConfigurationFields\ViewModel\UiSelectField;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\TestFramework\Fixture\AppArea;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureBeforeTransaction;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * The category select as the configuration page renders it through the real layout, container and catalog: the
 * widget's markup and every hook its script uses, escaping, one label wrapper around the field's own before and after
 * HTML, the notice for a type it cannot draw, the categories of the store view the page's scope shows, and the cache
 * entry that keeps them under this module's own id
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class UiSelectTest extends FieldRenderTestCase
{
    private const ADMIN_LABEL = 'Admin <b>name</b> & ${ $.provider }';
    private const STORE_LABEL = 'Store view name';
    private const RENAMED_LABEL = 'Renamed in the admin';
    private const SCOPE_STORE_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedCategories();
    }

    protected function tearDown(): void
    {
        $this->forgetCachedCategories();
        parent::tearDown();
    }

    public function testRendersTheWidgetWithEveryHookAndEscapesTheIdAndTheValue(): void
    {
        $html = $this->renderElement(
            UiSelect::class,
            $this->element(['value' => '2,3</script>'], 'multiselect')
        );
        $xpath = $this->dom($html);
        $rootId = $this->rootId();
        $componentName = str_replace('-', '_', $rootId) . '_select';

        $root = $this->role($xpath, 'hcf-ui-select');
        self::assertSame(['hui-tokens', 'hcf-ui-select'], $this->classes($root));
        self::assertSame($rootId, $root->getAttribute('id'));
        self::assertFalse($root->hasAttribute('data-disabled'));
        self::assertSame(
            "scope: '" . $componentName . "'",
            $this->role($xpath, 'hcf-ui-select-control')->getAttribute('data-bind')
        );

        $value = $this->role($xpath, 'hcf-ui-select-value');
        self::assertSame('hidden', $value->getAttribute('type'));
        self::assertSame(self::HTML_ID, $value->getAttribute('id'));
        self::assertSame(
            self::NAME . '[]',
            $value->getAttribute('name'),
            'A multiselect element names its value as a list, and the ids are posted as its one entry'
        );
        self::assertSame('2,3</script>', $value->getAttribute('value'));
        self::assertFalse($value->hasAttribute('disabled'));
        self::assertStringContainsString('id="cfg_field&quot;&lt;x&gt;"', $html);

        $config = $this->componentConfig($xpath, $rootId, UiSelectField::COMPONENT);
        self::assertSame($componentName, $config['componentName']);
        self::assertSame(self::HTML_ID . '_inherit', $config['inheritId']);
        self::assertTrue($config['multiple']);
        self::assertFalse($config['disabled']);
        self::assertSame('2,3</script>', $config['value']);
        self::assertIsArray($config['categories']);
    }

    public function testASelectTakesOneId(): void
    {
        $xpath = $this->dom($this->renderElement(UiSelect::class, $this->element(['value' => '3'], 'select')));

        $config = $this->componentConfig($xpath, $this->rootId(), UiSelectField::COMPONENT);
        self::assertFalse($config['multiple']);
        self::assertSame('3', $config['value']);
        $value = $this->role($xpath, 'hcf-ui-select-value');
        self::assertSame('3', $value->getAttribute('value'));
        self::assertSame(self::NAME, $value->getAttribute('name'));
    }

    public function testAnInheritedFieldIsDisabled(): void
    {
        $xpath = $this->dom($this->renderElement(UiSelect::class, $this->element([
            'value' => '3',
            'inherit' => 1,
            'can_use_default_value' => true,
            'scope' => 'websites',
            'scope_id' => '1',
        ], 'select')));

        self::assertSame('true', $this->role($xpath, 'hcf-ui-select')->getAttribute('data-disabled'));
        self::assertTrue($this->role($xpath, 'hcf-ui-select-value')->hasAttribute('disabled'));
        self::assertTrue($this->componentConfig($xpath, $this->rootId(), UiSelectField::COMPONENT)['disabled']);
    }

    public function testATypeItCannotDrawIsNamedInAnEscapedNoticeAndPostsNothing(): void
    {
        $element = $this->element(['value' => '3']);
        $element->setType('<img src=x onerror=alert(1)>');

        $html = $this->renderElement(UiSelect::class, $element);
        $xpath = $this->dom($html);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString(
            'The field type "<img src=x onerror=alert(1)>" cannot be drawn as a category select.',
            $this->role($xpath, 'hcf-ui-select-unsupported')->textContent
        );
        self::assertSame(0, $this->nodeCount($xpath, '//input[@name="' . self::NAME . '"]'));
        self::assertSame(0, $this->nodeCount($xpath, '//script[@type="text/x-magento-init"]'));
    }

    public function testTheFieldsBeforeAndAfterHtmlAreWrappedOnceAndTheWidgetSitsInNoLabel(): void
    {
        $xpath = $this->dom($this->renderElement(UiSelect::class, $this->element([
            'value' => '3',
            'before_element_html' => '<em>before</em>',
            'after_element_html' => '<em>after</em>',
        ], 'select')));

        self::assertSame(1, $this->nodeCount($xpath, '//label[contains(@class, "addbefore")]'));
        self::assertSame(1, $this->nodeCount($xpath, '//label[contains(@class, "addafter")]'));
        self::assertSame(1, $this->nodeCount($xpath, '//label[contains(@class, "addafter")]/em'));
        self::assertSame(0, $this->nodeCount($xpath, '//label//*[@data-role="hcf-ui-select"]'));
    }

    #[DataFixtureBeforeTransaction(StoreFixture::class, [], 'second_store')]
    #[DataFixture(CategoryFixture::class, ['name' => self::ADMIN_LABEL], 'category')]
    public function testTheCategoriesAreLabelledInTheStoreViewThePageScopeShows(): void
    {
        $categoryId = $this->fixtureCategoryId();
        $this->nameInStore($categoryId, self::SCOPE_STORE_ID, self::STORE_LABEL);
        $secondStoreId = $this->fixtureId('second_store');

        self::assertSame(
            self::ADMIN_LABEL,
            $this->renderedLabel($categoryId, ['scope' => 'default', 'scope_id' => ''])
        );
        self::assertSame(
            self::STORE_LABEL,
            $this->renderedLabel($categoryId, ['scope' => 'stores', 'scope_id' => (string) self::SCOPE_STORE_ID])
        );
        self::assertSame(
            self::STORE_LABEL,
            $this->renderedLabel($categoryId, ['scope' => 'websites', 'scope_id' => '1']),
            'A website shows its default store view'
        );
        self::assertSame(
            self::ADMIN_LABEL,
            $this->renderedLabel($categoryId, ['scope' => 'stores', 'scope_id' => $secondStoreId]),
            'Another store view of the website keeps the admin name it has no own name for'
        );
    }

    #[DataFixture(CategoryFixture::class, ['name' => self::ADMIN_LABEL], 'category')]
    public function testTheCategoriesAreKeptUnderTheModulesOwnIdUntilTheCategoryTagIsCleaned(): void
    {
        $categoryId = $this->fixtureCategoryId();
        $storeScope = ['scope' => 'stores', 'scope_id' => (string) self::SCOPE_STORE_ID];
        $cache = $this->cache();

        self::assertSame(self::ADMIN_LABEL, $this->renderedLabel($categoryId, $storeScope));
        self::assertStringContainsString(
            '"id":"' . $categoryId . '"',
            (string) $cache->load(CachedCategoryOptionSource::CACHE_ID_PREFIX . self::SCOPE_STORE_ID),
            'The rows are kept under the module\'s own id for the store view'
        );

        $this->nameInStore($categoryId, 0, self::RENAMED_LABEL);
        self::assertSame(
            self::ADMIN_LABEL,
            $this->renderedLabel($categoryId, $storeScope),
            'A kept entry is served without reading the catalog'
        );

        $cache->clean([Category::CACHE_TAG]);
        self::assertSame(self::RENAMED_LABEL, $this->renderedLabel($categoryId, $storeScope));
    }

    /**
     * The label the rendered field gives a category, at the given scope
     *
     * @param string $categoryId
     * @param array<string, string> $scope
     * @return string
     */
    private function renderedLabel(string $categoryId, array $scope): string
    {
        $xpath = $this->dom($this->renderElement(UiSelect::class, $this->element(['value' => ''] + $scope, 'select')));
        $categories = $this->componentConfig($xpath, $this->rootId(), UiSelectField::COMPONENT)['categories'];
        self::assertIsArray($categories);

        foreach ($categories as $row) {
            self::assertIsArray($row);
            if (($row['id'] ?? null) === $categoryId) {
                self::assertIsString($row['label']);

                return $row['label'];
            }
        }

        self::fail('The category ' . $categoryId . ' is not offered');
    }

    /**
     * Gives a category its own name in a store view; the catalog keeps a store view's own value only while the
     * installation has more than one store view, which the test's second store view ensures
     *
     * @param string $categoryId
     * @param int $storeId
     * @param string $name
     * @return void
     */
    private function nameInStore(string $categoryId, int $storeId, string $name): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $repository = $objectManager->get(CategoryRepositoryInterface::class);
        self::assertInstanceOf(CategoryRepositoryInterface::class, $repository);
        $category = $repository->get((int) $categoryId, $storeId);
        self::assertInstanceOf(Category::class, $category);
        $category->setStoreId($storeId);
        $category->setName($name);
        $resource = $objectManager->get(CategoryResource::class);
        self::assertInstanceOf(CategoryResource::class, $resource);
        $resource->saveAttribute($category, 'name');
    }

    /**
     * @return string
     */
    private function fixtureCategoryId(): string
    {
        return $this->fixtureId('category');
    }

    /**
     * The id of an object a data fixture created
     *
     * @param string $name The fixture's name
     * @return string
     */
    private function fixtureId(string $name): string
    {
        $storage = DataFixtureStorageManager::getStorage();
        self::assertInstanceOf(DataFixtureStorage::class, $storage);
        $created = $storage->get($name);
        self::assertInstanceOf(AbstractModel::class, $created);
        $id = $created->getId();
        self::assertIsNumeric($id);

        return (string) $id;
    }

    /**
     * How many nodes a query finds
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @return int
     */
    private function nodeCount(\DOMXPath $xpath, string $query): int
    {
        $nodes = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);

        return $nodes->length;
    }

    /**
     * Drops the kept categories, so each test starts from the catalog and leaves no entry behind
     *
     * @return void
     */
    private function forgetCachedCategories(): void
    {
        $this->cache()->clean([Category::CACHE_TAG]);
    }

    /**
     * @return CacheInterface
     */
    private function cache(): CacheInterface
    {
        $cache = Bootstrap::getObjectManager()->get(CacheInterface::class);
        self::assertInstanceOf(CacheInterface::class, $cache);

        return $cache;
    }
}
