<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Cache\Type\Block;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * UI Select field for system configuration
 */
class UiSelect extends Field
{
    /**
     * Category tree cache identifier
     */
    private const CATEGORY_TREE_ID = 'CATALOG_PRODUCT_CATEGORY_TREE';

    public function __construct(
        Context $context,
        private readonly LocatorInterface $locator,
        private readonly SerializerInterface $serializer,
        private readonly CacheInterface $cacheManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Session $session,
        private readonly DbHelper $dbHelper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $html = '';
        $htmlId = $element->getHtmlId();
        $value = $this->prepareElementValue($element);

        // Add before element HTML if exists
        $beforeElementHtml = $element->getBeforeElementHtml();
        if ($beforeElementHtml) {
            $html .= sprintf(
                '<label class="addbefore" for="%s">%s</label>',
                $htmlId,
                $beforeElementHtml
            );
        }

        if ($element->getType() !== 'select' && $element->getType() !== 'multiselect') {
            $html .= '<div>Field type ' . $element->getType() . ' is not supported. Please use "select" or "multiselect".</div>';
        } else {
            // Main UI component HTML
            $html .= sprintf(
                '<div id="%s" data-bind="scope: \'ui_select_categories\'">
                <input type="hidden" name="%s" value="%s" data-bind="value: value" />
                <!-- ko template: elementTmpl --><!-- /ko -->
            </div>',
                $element->getId(),
                $element->getName(),
                $value
            );
        }

        $html .= $this->getUiComponentInitializationScript($element);

        // Add JS after element if exists
        $afterElementJs = $element->getAfterElementJs();
        if ($afterElementJs) {
            $html .= $afterElementJs;
        }

        // Add after element HTML if exists
        $afterElementHtml = $element->getAfterElementHtml();
        if ($afterElementHtml) {
            $html .= sprintf(
                '<label class="addafter" for="%s">%s</label>',
                $htmlId,
                $afterElementHtml
            );
        }

        return $html;
    }

    /**
     * Format element value for display
     *
     * @param AbstractElement $element
     * @return string
     */
    private function prepareElementValue(AbstractElement $element): string
    {
        $value = $element->getEscapedValue();

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return (string)$value;
    }

    /**
     * Generate UI component initialization script
     *
     * @param AbstractElement $element
     * @return string
     */
    public function getUiComponentInitializationScript(AbstractElement $element): string
    {
        $options = $this->getCategoriesTree();
        $configJson = json_encode($options);
        $value = $this->getElementValueForJs($element);

        // Check if $element is multiselect
        $config = $element->getFieldConfig();
        if (!isset($config['type'])) {
            $config['type'] = 'select';
        }
        $isMultiSelect = $config['type'] === 'multiselect';
        $multiple = $isMultiSelect ? 'true' : 'false';

        return <<<HTML
<script type="text/x-magento-init">
{
    "#{$element->getId()}": {
        "Magento_Ui/js/core/app": {
            "components": {
                "ui_select_categories": {
                    "formElement": "select",
                    "componentType": "field",
                    "component": "Magento_Ui/js/form/element/ui-select",
                    "elementTmpl": "ui/grid/filters/elements/ui-select",
                    "filterOptions": true,
                    "chipsEnabled": true,
                    "disableLabel": true,
                    "levelsVisibility": "1",
                    "options": $configJson,
                    "value": $value,
                    "multiple": $multiple
                }
            }
        }
    }
}
</script>
HTML;
    }

    /**
     * Get element value in format suitable for JS component
     *
     * @param AbstractElement $element
     * @return string
     */
    private function getElementValueForJs(AbstractElement $element): string
    {
        $value = $element->getEscapedValue();

        if ((is_array($value) === false && $value === null) || $value === '') {
            $value = [];
        } elseif (is_string($value)) {
            $value = explode(',', $value);
        }

        return json_encode($value);
    }

    /**
     * Retrieve categories tree
     *
     * @param string|null $filter
     * @return array
     * @throws LocalizedException
     */
    protected function getCategoriesTree(?string $filter = null): array
    {
        $storeId = $this->getStoreId();
        $cacheId = $this->getCategoriesTreeCacheId($storeId, (string)$filter);

        // Try to load from cache first
        $cachedCategoriesTree = $this->cacheManager->load($cacheId);
        if (!empty($cachedCategoriesTree)) {
            return $this->serializer->unserialize($cachedCategoriesTree);
        }

        // Build categories tree
        $shownCategoriesIds = $this->retrieveShownCategoriesIds($storeId, (string)$filter);
        $categoriesTree = $this->buildCategoriesTree($storeId, $shownCategoriesIds);

        // Save to cache
        $this->cacheManager->save(
            $this->serializer->serialize($categoriesTree),
            $cacheId,
            [
                CategoryModel::CACHE_TAG,
                Block::CACHE_TAG
            ]
        );

        return $categoriesTree;
    }

    /**
     * Get current store ID
     *
     * @return int
     */
    private function getStoreId(): int
    {
        try {
            return (int)$this->locator->getStore()->getId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache ID for categories tree
     *
     * @param int $storeId
     * @param string $filter
     * @return string
     */
    private function getCategoriesTreeCacheId(int $storeId, string $filter = ''): string
    {
        $cacheId = self::CATEGORY_TREE_ID . '_' . (string)$storeId;

        if ($this->session->getUser() !== null) {
            $cacheId .= '_' . $this->session->getUser()->getAclRole();
        }

        if (!empty($filter)) {
            $cacheId .= '_' . $filter;
        }

        return $cacheId;
    }

    /**
     * Build tree of categories with attributes
     *
     * @param int $storeId
     * @param array $shownCategoriesIds
     * @return array|null
     * @throws LocalizedException
     */
    private function buildCategoriesTree(int $storeId, array $shownCategoriesIds): ?array
    {
        $collection = $this->getCategoryCollection($storeId, $shownCategoriesIds);
        $categoryById = $this->initCategoryTree();

        foreach ($collection as $category) {
            $this->processCategoryForTree($category, $categoryById);
        }

        return $categoryById[CategoryModel::TREE_ROOT_ID]['optgroup'] ?? null;
    }

    /**
     * Get category collection
     *
     * @param int $storeId
     * @param array $shownCategoriesIds
     * @return Collection
     */
    private function getCategoryCollection(int $storeId, array $shownCategoriesIds): Collection
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('entity_id', ['in' => array_keys($shownCategoriesIds)])
            ->addAttributeToSelect(['name', 'is_active', 'parent_id'])
            ->setStoreId($storeId);

        return $collection;
    }

    /**
     * Initialize category tree array
     *
     * @return array
     */
    private function initCategoryTree(): array
    {
        return [
            CategoryModel::TREE_ROOT_ID => [
                'value' => CategoryModel::TREE_ROOT_ID,
                'optgroup' => null,
            ],
        ];
    }

    /**
     * Process category for tree building
     *
     * @param CategoryModel $category
     * @param array $categoryById
     * @return void
     */
    private function processCategoryForTree(CategoryModel $category, array &$categoryById): void
    {
        // Initialize category and parent nodes if needed
        foreach ([$category->getId(), $category->getParentId()] as $categoryId) {
            if (!isset($categoryById[$categoryId])) {
                $categoryById[$categoryId] = ['value' => $categoryId];
            }
        }

        // Add category data
        $categoryById[$category->getId()]['is_active'] = $category->getIsActive();
        $categoryById[$category->getId()]['label'] = (string)$category->getName();
        $categoryById[$category->getId()]['__disableTmpl'] = true;
        $categoryById[$category->getParentId()]['optgroup'][] = &$categoryById[$category->getId()];
    }

    /**
     * Retrieve filtered list of categories IDs
     *
     * @param int $storeId
     * @param string $filter
     * @return array
     * @throws LocalizedException
     */
    private function retrieveShownCategoriesIds(int $storeId, string $filter = ''): array
    {
        $collection = $this->categoryCollectionFactory->create();

        // Apply name filter if provided
        if (!empty($filter)) {
            $collection->addAttributeToFilter(
                'name',
                ['like' => $this->dbHelper->addLikeEscape($filter, ['position' => 'any'])]
            );
        }

        $collection->addAttributeToSelect('path')
            ->addAttributeToFilter('entity_id', ['neq' => CategoryModel::TREE_ROOT_ID])
            ->setStoreId($storeId);

        return $this->extractPathIds($collection);
    }

    /**
     * Extract all parent IDs from category paths
     *
     * @param Collection $collection
     * @return array
     */
    private function extractPathIds(Collection $collection): array
    {
        $shownCategoriesIds = [];

        /** @var CategoryModel $category */
        foreach ($collection as $category) {
            $path = $category->getPath();
            if ($path) {
                foreach (explode('/', $path) as $parentId) {
                    $shownCategoriesIds[$parentId] = 1;
                }
            }
        }

        return $shownCategoriesIds;
    }
}