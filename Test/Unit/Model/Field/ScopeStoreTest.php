<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\ScopeStore;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * The store view a configuration field's values are shown in follows the scope the configuration page edits
 */
class ScopeStoreTest extends FormElementTestCase
{
    public function testAStoreViewScopeShowsThatStoreView(): void
    {
        self::assertSame(4, $this->scopeStore()->storeId($this->element(['scope' => 'stores', 'scope_id' => '4'])));
    }

    public function testAWebsiteScopeShowsTheWebsitesDefaultStoreView(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getDefaultGroupId')->willReturn(5);
        $group = $this->createMock(GroupInterface::class);
        $group->method('getDefaultStoreId')->willReturn(7);
        $storeManager = $this->storeManager();
        $storeManager->method('getWebsite')->with(2)->willReturn($website);
        $storeManager->method('getGroup')->with('5')->willReturn($group);

        self::assertSame(
            7,
            (new ScopeStore($storeManager))->storeId($this->element(['scope' => 'websites', 'scope_id' => 2]))
        );
    }

    public function testTheDefaultScopeShowsTheAdminStore(): void
    {
        self::assertSame(0, $this->scopeStore()->storeId($this->element(['scope' => 'default', 'scope_id' => ''])));
    }

    public function testAnElementWithoutAScopeShowsTheAdminStore(): void
    {
        self::assertSame(0, $this->scopeStore()->storeId($this->element([])));
        self::assertSame(0, $this->scopeStore()->storeId($this->element(['scope' => 'stores', 'scope_id' => 'x'])));
    }

    public function testAWebsiteThatCannotBeResolvedShowsTheAdminStore(): void
    {
        $storeManager = $this->storeManager();
        $storeManager->method('getWebsite')->willThrowException(new LocalizedException(__('No such website.')));

        self::assertSame(
            0,
            (new ScopeStore($storeManager))->storeId($this->element(['scope' => 'websites', 'scope_id' => '9']))
        );
    }

    public function testAWebsiteWhoseDefaultGroupCannotBeResolvedShowsTheAdminStore(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getDefaultGroupId')->willReturn(0);
        $storeManager = $this->storeManager();
        $storeManager->method('getWebsite')->willReturn($website);
        $storeManager->method('getGroup')->willThrowException(new NoSuchEntityException(__('No such group.')));

        self::assertSame(
            0,
            (new ScopeStore($storeManager))->storeId($this->element(['scope' => 'websites', 'scope_id' => '1']))
        );
    }

    /**
     * @return ScopeStore
     */
    private function scopeStore(): ScopeStore
    {
        return new ScopeStore($this->storeManager());
    }

    /**
     * @return StoreManagerInterface&MockObject
     */
    private function storeManager(): StoreManagerInterface&MockObject
    {
        return $this->createMock(StoreManagerInterface::class);
    }
}
