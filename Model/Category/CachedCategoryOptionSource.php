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
use Magento\Framework\App\Cache\Type\Block;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Authorization\RoleLocatorInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Keeps the categories in Magento's cache, one entry per store view and admin role
 *
 * The entry's id starts with this module's own prefix, so it never shares an id with another module's category tree.
 * It holds the store view, because the labels are that store view's names, and the admin's role, because a role whose
 * catalog access is restricted may be shown fewer categories. The entry is tagged with the category tag and the block
 * cache's tag: it is dropped whenever either is cleaned, and lives until then. An entry that does not decode to a list
 * of category rows is read again from the catalog and replaced.
 */
class CachedCategoryOptionSource implements CategoryOptionSourceInterface
{
    public const CACHE_ID_PREFIX = 'HRYVINSKYI_CONFIGURATION_FIELDS_CATEGORY_OPTIONS_';

    /**
     * @param CategoryOptionSourceInterface $source The source that reads the catalog
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RoleLocatorInterface $roleLocator The signed-in admin's role
     */
    public function __construct(
        private readonly CategoryOptionSourceInterface $source,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly RoleLocatorInterface $roleLocator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function categories(int $storeId): array
    {
        $cacheId = $this->cacheId($storeId);
        $entry = (string) $this->cache->load($cacheId);
        $cached = $entry !== '' ? $this->decode($entry) : null;
        if ($cached !== null) {
            return $cached;
        }

        $rows = $this->source->categories($storeId);
        $encoded = $this->serializer->serialize($rows);
        if (is_string($encoded)) {
            $this->cache->save($encoded, $cacheId, [Category::CACHE_TAG, Block::CACHE_TAG]);
        }

        return $rows;
    }

    /**
     * The entry's id: the prefix, the store view and, for a signed-in admin, the role
     *
     * @param int $storeId
     * @return string
     */
    private function cacheId(int $storeId): string
    {
        $cacheId = self::CACHE_ID_PREFIX . $storeId;
        $role = $this->roleLocator->getAclRoleId();
        if (is_scalar($role) && (string) $role !== '') {
            $cacheId .= '_' . $role;
        }

        return $cacheId;
    }

    /**
     * The rows an entry holds, or null when it does not hold a list of category rows
     *
     * @param string $entry
     * @return list<array{id: string, parentId: string, label: string}>|null
     */
    private function decode(string $entry): ?array
    {
        try {
            $decoded = $this->serializer->unserialize($entry);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)
                || !is_string($row['id'] ?? null)
                || !is_string($row['parentId'] ?? null)
                || !is_string($row['label'] ?? null)
            ) {
                return null;
            }
            $rows[] = ['id' => $row['id'], 'parentId' => $row['parentId'], 'label' => $row['label']];
        }

        return $rows;
    }
}
