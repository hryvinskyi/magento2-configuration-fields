<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The store view whose names a configuration field shows, read from the scope the configuration page edits
 *
 * Magento's configuration form gives every element the scope it edits (`scope`) and that scope's id (`scope_id`). At
 * store-view scope the field shows that store view; at website scope, the website's default store view, which is what
 * the website's visitors see; at default scope, and for an element that carries no scope, the admin store. A website
 * whose default store view cannot be resolved also shows the admin store, so a field never stops the page.
 */
class ScopeStore
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The id of the store view the field's values are shown in
     *
     * @param AbstractElement $element
     * @return int
     */
    public function storeId(AbstractElement $element): int
    {
        $scope = $element->getData('scope');
        $scopeId = $element->getData('scope_id');
        if (!is_numeric($scopeId)) {
            return Store::DEFAULT_STORE_ID;
        }
        if ($scope === ScopeInterface::SCOPE_STORES) {
            return (int) $scopeId;
        }
        if ($scope === ScopeInterface::SCOPE_WEBSITES) {
            return $this->websiteStoreId((int) $scopeId);
        }

        return Store::DEFAULT_STORE_ID;
    }

    /**
     * The default store view of a website, or the admin store when it cannot be resolved
     *
     * @param int $websiteId
     * @return int
     */
    private function websiteStoreId(int $websiteId): int
    {
        try {
            $groupId = (string) $this->storeManager->getWebsite($websiteId)->getDefaultGroupId();

            return (int) $this->storeManager->getGroup($groupId)->getDefaultStoreId();
        } catch (LocalizedException) {
            return Store::DEFAULT_STORE_ID;
        }
    }
}
