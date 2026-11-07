<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Controller\Adminhtml\System;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Stores › Configuration through the real admin stack: the page links the admin UI kit's stylesheet and the module's
 * own stylesheets for its fields, the kit's first
 */
class ConfigPageTest extends AbstractBackendController
{
    private const KIT_STYLESHEET = 'Hryvinskyi_AdminUi::css/admin-ui.css';

    private const MODULE_STYLESHEETS = [
        'Hryvinskyi_ConfigurationFields::css/cron-editor.css',
        'Hryvinskyi_ConfigurationFields::css/tag-list.css',
        'Hryvinskyi_ConfigurationFields::css/codemirror-editor.css',
        'Hryvinskyi_ConfigurationFields::css/ui-select.css',
    ];

    /**
     * @var string
     */
    protected $resource = 'Magento_Config::config';

    /**
     * @var string
     */
    protected $uri = 'backend/admin/system_config/edit/section/general';

    /**
     * The kit's stylesheet is linked in the head before each of the module's, whose rules override kit rules of the
     * same weight by that order, and every one of them resolves to a source file
     *
     * A link's address is built without touching the file it names, so each source file is resolved on its own.
     *
     * @return void
     */
    public function testThePageLinksTheKitStylesheetBeforeTheModulesOwn(): void
    {
        $this->dispatch($this->uri);
        $links = $this->stylesheetLinks();

        $kit = $this->position($links, self::KIT_STYLESHEET);
        self::assertIsInt($kit, 'The kit stylesheet is linked in the head');
        foreach (self::MODULE_STYLESHEETS as $stylesheet) {
            $own = $this->position($links, $stylesheet);
            self::assertIsInt($own, $stylesheet . ' is linked in the head');
            self::assertLessThan($own, $kit, 'The kit comes before ' . $stylesheet . ', which overrides it by order');
        }

        $assets = $this->_objectManager->get(AssetRepository::class);
        self::assertInstanceOf(AssetRepository::class, $assets);
        foreach ([self::KIT_STYLESHEET, ...self::MODULE_STYLESHEETS] as $stylesheet) {
            self::assertFileExists($assets->createAsset($stylesheet, ['area' => 'adminhtml'])->getSourceFile());
        }
    }

    /**
     * The position of the link to this asset among the head's stylesheet links, or null when none links it
     *
     * @param list<string> $links
     * @param string $assetId An asset id, `Vendor_Module::path`
     * @return int|null
     */
    private function position(array $links, string $assetId): ?int
    {
        $path = '/' . str_replace('::', '/', $assetId);
        foreach ($links as $index => $href) {
            if (str_ends_with($href, $path)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The addresses of the head's stylesheet links, in document order
     *
     * @return list<string>
     */
    private function stylesheetLinks(): array
    {
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML((string) $response->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING));
        $nodes = (new DOMXPath($document))->query('//head/link[@rel="stylesheet"]');
        self::assertNotFalse($nodes);

        $links = [];
        foreach ($nodes as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $links[] = $node->getAttribute('href');
        }

        return $links;
    }
}
