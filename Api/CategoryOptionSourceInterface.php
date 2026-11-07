<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Api;

/**
 * The catalog's categories a category select field offers
 *
 * Each category is one flat row naming its parent; the select nests the rows into its tree. The catalog's root itself
 * is not a row, so a row whose parent is not listed is at the top of the tree. Ids are strings, as a field stores and
 * posts them.
 */
interface CategoryOptionSourceInterface
{
    /**
     * Every category below the catalog's root, labelled in the given store view, in the catalog's order
     *
     * @param int $storeId The store view whose names label the categories; 0 for the admin names
     * @return list<array{id: string, parentId: string, label: string}>
     */
    public function categories(int $storeId): array;
}
