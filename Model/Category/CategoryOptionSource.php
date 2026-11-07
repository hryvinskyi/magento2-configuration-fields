<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Category;

use Hryvinskyi\ConfigurationFields\Api\CategoryOptionSourceInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\DataObject;

/**
 * Reads the categories from the catalog in one collection: every category except the catalog's root, with its name
 * in the store view asked for (the admin name where the store view has none), siblings in their catalog position
 *
 * A row without an id or a parent cannot be placed in the tree and is left out. A name that is not valid UTF-8 has its
 * malformed bytes replaced, so writing the rows as JSON cannot fail and leave the configuration section empty.
 */
class CategoryOptionSource implements CategoryOptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function categories(int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['neq' => Category::TREE_ROOT_ID]);
        $collection->setOrder('position', 'ASC');

        $rows = [];
        foreach ($collection->getItems() as $category) {
            $row = $this->row($category);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * One category as a row, or null when it has no id or no parent
     *
     * @param DataObject $category
     * @return array{id: string, parentId: string, label: string}|null
     */
    private function row(DataObject $category): ?array
    {
        $id = $category->getData('entity_id');
        $parentId = $category->getData('parent_id');
        if (!is_scalar($id) || !is_scalar($parentId) || (string) $id === '' || (string) $parentId === '') {
            return null;
        }
        $name = $category->getData('name');

        return [
            'id' => (string) $id,
            'parentId' => (string) $parentId,
            'label' => is_scalar($name) ? mb_scrub((string) $name, 'UTF-8') : '',
        ];
    }
}
