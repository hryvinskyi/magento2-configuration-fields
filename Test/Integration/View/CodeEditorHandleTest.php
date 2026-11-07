<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\View;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Magento\Framework\App\Area;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Page\Config\RendererFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The code editor's view files for a UI form, as Magento resolves and links them in the admin
 *
 * The UI form element asks RequireJS for its scripts and Knockout for its template by name at run time, and the
 * page's layout asks for the stylesheet, so a moved or renamed file fails in the browser and nowhere else. Each file
 * is resolved through the admin's view-file fallback. The layout handle a form page asks for is rendered into a page
 * head, where the admin UI kit's stylesheet must come before the editor's: the editor reads the kit's tokens, and a
 * rule of the editor's overrides a kit rule of the same weight by that order.
 */
#[AppArea(Area::AREA_ADMINHTML)]
class CodeEditorHandleTest extends TestCase
{
    private const HANDLE = 'hryvinskyi_configuration_fields_code_editor';
    private const KIT_STYLESHEET = 'Hryvinskyi_AdminUi/css/admin-ui.css';
    private const EDITOR_STYLESHEET = 'Hryvinskyi_ConfigurationFields/css/codemirror-editor.css';

    /**
     * The element's component, its template, the modules the component requires and the stylesheet the handle links
     *
     * @return array<string, array{string}>
     */
    public static function viewFiles(): array
    {
        return [
            'stylesheet' => ['Hryvinskyi_ConfigurationFields::css/codemirror-editor.css'],
            'element template' => ['Hryvinskyi_ConfigurationFields::template/form/element/codemirror.html'],
            'element script' => ['Hryvinskyi_ConfigurationFields::js/form/element/codemirror.js'],
            'setup script' => ['Hryvinskyi_ConfigurationFields::js/codemirror-setup.js'],
            'stylesheet link script' => ['Hryvinskyi_ConfigurationFields::js/stylesheet-link.js'],
            'CodeMirror script' => ['Hryvinskyi_ConfigurationFields::js/codemirror/lib/codemirror.js'],
            'CodeMirror stylesheet' => ['Hryvinskyi_ConfigurationFields::js/codemirror/lib/codemirror.css'],
            'fold gutter stylesheet' => ['Hryvinskyi_ConfigurationFields::css/foldgutter.css'],
        ];
    }

    /**
     * Magento resolves the file through the admin's view-file fallback to a file that exists
     *
     * @param string $fileId
     * @return void
     */
    #[DataProvider('viewFiles')]
    public function testTheAdminResolvesTheViewFile(string $fileId): void
    {
        $assets = Bootstrap::getObjectManager()->get(AssetRepository::class);
        self::assertInstanceOf(AssetRepository::class, $assets);

        $source = $assets->createAsset($fileId, ['area' => Area::AREA_ADMINHTML])->getSourceFile();

        self::assertFileExists($source, $fileId . ' resolves to a file');
    }

    /**
     * A page that asks for the handle links the kit's stylesheet in its head, then the editor's
     *
     * The page's head configuration is one shared instance per application, so each head is built in an
     * application of its own.
     *
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheHandleLinksTheKitStylesheetBeforeTheEditorsOwn(): void
    {
        $hrefs = $this->stylesheetsInTheHeadOf(self::HANDLE);

        $kit = $this->positionOf(self::KIT_STYLESHEET, $hrefs);
        $editor = $this->positionOf(self::EDITOR_STYLESHEET, $hrefs);
        self::assertIsInt($kit, 'The kit stylesheet is linked in the head');
        self::assertIsInt($editor, 'The editor stylesheet is linked in the head');
        self::assertLessThan($editor, $kit, 'The kit comes first, so the editor overrides it by order');
    }

    /**
     * A page that does not ask for the handle links neither stylesheet, so the handle is what brings them
     *
     * @return void
     */
    #[AppIsolation(true)]
    public function testAPageWithoutTheHandleLinksNeitherStylesheet(): void
    {
        $hrefs = $this->stylesheetsInTheHeadOf(null);

        self::assertNull($this->positionOf(self::KIT_STYLESHEET, $hrefs));
        self::assertNull($this->positionOf(self::EDITOR_STYLESHEET, $hrefs));
    }

    /**
     * The addresses of the stylesheets an admin page result renders into its head, in their order
     *
     * @param string|null $handle the layout handle the page asks for, or none
     * @return list<string>
     */
    private function stylesheetsInTheHeadOf(?string $handle): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $pages = $objectManager->get(PageFactory::class);
        self::assertInstanceOf(PageFactory::class, $pages);
        $renderers = $objectManager->get(RendererFactory::class);
        self::assertInstanceOf(RendererFactory::class, $renderers);

        $page = $pages->create();
        if ($handle !== null) {
            $page->addHandle($handle);
        }
        $config = $page->getConfig();
        $config->publicBuild();
        $head = $renderers->create(['pageConfig' => $config])->renderHeadAssets();

        $document = new DOMDocument();
        self::assertTrue($document->loadHTML('<html><head>' . $head . '</head></html>', LIBXML_NOERROR));
        $links = (new DOMXPath($document))->query('//head/link[@rel="stylesheet"]');
        self::assertNotFalse($links);
        $hrefs = [];
        foreach ($links as $link) {
            self::assertInstanceOf(DOMElement::class, $link);
            $hrefs[] = $link->getAttribute('href');
        }

        return $hrefs;
    }

    /**
     * The position of the first address that names this file, or null when none does
     *
     * @param string $file
     * @param list<string> $hrefs
     * @return int|null
     */
    private function positionOf(string $file, array $hrefs): ?int
    {
        foreach ($hrefs as $position => $href) {
            if (str_contains($href, $file)) {
                return $position;
            }
        }

        return null;
    }
}
