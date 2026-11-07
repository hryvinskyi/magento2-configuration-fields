<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Model\TagList\FieldBlock\DeclaredFieldBlockConfig;
use Hryvinskyi\ConfigurationFields\Model\TagList\FrontendModelSettingsSource;
use Magento\Config\Model\Config\PathValidator;
use Magento\Config\Model\Config;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Data as StructureData;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager\ConfigLoader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Saves a section of test fields the way Stores › Configuration does, through Magento's own config model, with the
 * fields declared here instead of in a `system.xml` the module would ship
 *
 * While a test runs, the configuration structure the container builds holds only the test section: the shared
 * structure data is replaced, and the shared objects built from the real data (the structure, its proxy, the path
 * validator of `config:set`, the tag-list field settings source) are dropped, so the configuration form's save,
 * `config:set` and whatever reads a field's definition by its path during the save all find the test fields. Each is
 * dropped again after the test, so the next one is built from the real data. As Magento's own structure data, the
 * test section is there only while the configuration scope is the admin area's, where `system.xml` files are read:
 * the structure built for the area a request runs in holds it in the admin, and the one built for the admin's scope
 * (`adminhtmlConfigStructure`) holds it everywhere.
 *
 * A test can also declare container types for the admin area alone (declareForTheAdminAreaOnly()), as a consumer
 * declares its tag-list field blocks in its `etc/adminhtml/di.xml`: the loader of each area's declarations is replaced
 * by one that adds them to the admin area's and counts every load, and the real loader is put back after the test.
 *
 * Every field is built from its definition as the configuration form builds it (its backend model created by the
 * container), and the section is saved in one transaction, so a field that refuses its value stores nothing for the
 * whole section. A test of a refused save runs without database isolation (`#[DbIsolation(false)]`): the isolating
 * transaction would turn the section's rollback into a no-op. The test section's rows are deleted before and after
 * each test instead.
 */
abstract class SectionSaveTestCase extends TestCase
{
    protected const SECTION = 'hcf_test_section';
    protected const GROUP = 'fields';

    /**
     * Shared objects built from the configuration structure, dropped whenever the structure data changes
     */
    private const BUILT_FROM_STRUCTURE = [
        StructureData::class,
        Structure::class,
        Structure\Proxy::class,
        PathValidator::class,
        FrontendModelSettingsSource::class,
        self::ADMIN_STRUCTURE_DATA,
        self::ADMIN_STRUCTURE,
    ];

    /**
     * Shared objects built from the areas' container declarations, dropped whenever the declarations change
     */
    private const BUILT_FROM_DECLARATIONS = [
        FrontendModelSettingsSource::class,
        DeclaredFieldBlockConfig::class,
    ];

    /**
     * Magento's configuration structure read for the admin area's scope, whatever area the code runs in, and its data
     */
    private const ADMIN_STRUCTURE = 'adminhtmlConfigStructure';
    private const ADMIN_STRUCTURE_DATA = 'adminhtmlConfigStructureData';
    private const ADMIN_SCOPE = 'adminhtmlConfigScope';

    /**
     * The real loader of the areas' declarations, while a test has replaced it
     *
     * @var ConfigLoaderInterface|null
     */
    private ?ConfigLoaderInterface $realDeclarations = null;

    protected function setUp(): void
    {
        $this->deleteStoredValues();
    }

    protected function tearDown(): void
    {
        $this->deleteStoredValues();
        $this->dropObjectsBuiltFromStructure();
        if ($this->realDeclarations !== null) {
            $this->useDeclarations($this->realDeclarations);
            $this->realDeclarations = null;
        }
    }

    /**
     * Declare container types for the admin area alone, as a consumer's `etc/adminhtml/di.xml` declares them
     *
     * The types are not applied to the running container: an area's declarations reach it only when the area is
     * loaded. The returned loader counts how often each area's declarations are loaded from now on.
     *
     * @param array<string, array<string, mixed>> $types Type name => its declaration (`type`, `arguments`)
     * @return AdminAreaDeclarations
     */
    protected function declareForTheAdminAreaOnly(array $types): AdminAreaDeclarations
    {
        $real = $this->realDeclarations ?? $this->objectManager()->get(ConfigLoader::class);
        self::assertInstanceOf(ConfigLoaderInterface::class, $real);
        $this->realDeclarations = $real;
        $declarations = new AdminAreaDeclarations($real, $types);
        $this->useDeclarations($declarations);

        return $declarations;
    }

    /**
     * Make the test section, with these fields, the only section the container's configuration structure holds
     *
     * @param array<string, array<string, string>> $fields Field id => definition (`label`, `backend_model`, …)
     * @return void
     */
    protected function useTestSection(array $fields): void
    {
        $this->dropObjectsBuiltFromStructure();
        $objectManager = $this->objectManager();
        $areaScope = $objectManager->get(ScopeInterface::class);
        $adminScope = $objectManager->get(self::ADMIN_SCOPE);
        self::assertInstanceOf(ScopeInterface::class, $areaScope);
        self::assertInstanceOf(ScopeInterface::class, $adminScope);
        $objectManager->addSharedInstance($this->structureData($fields, $areaScope), StructureData::class);
        $objectManager->addSharedInstance($this->structureData($fields, $adminScope), self::ADMIN_STRUCTURE_DATA);
    }

    /**
     * Save the test section with these fields posted at default scope
     *
     * @param array<string, array<string, string>> $fields Field id => definition (`label`, `backend_model`, …)
     * @param array<string, string> $posted Field id => posted value
     * @return void
     */
    protected function saveTestSection(array $fields, array $posted): void
    {
        $this->useTestSection($fields);
        $config = $this->objectManager()->create(Config::class);
        self::assertInstanceOf(Config::class, $config);

        $config->setData('section', self::SECTION);
        $config->setData('groups', [
            self::GROUP => [
                'fields' => array_map(static fn (string $value): array => ['value' => $value], $posted),
            ],
        ]);
        $config->save();
    }

    /**
     * Whether a row is stored for a test field at default scope
     *
     * @param string $fieldId
     * @return bool
     */
    protected function isStored(string $fieldId): bool
    {
        return $this->storedValues($fieldId) !== [];
    }

    /**
     * The value stored for a test field at default scope, or null when none is stored or it is null
     *
     * @param string $fieldId
     * @return string|null
     */
    protected function storedValue(string $fieldId): ?string
    {
        $value = $this->storedValues($fieldId)[0] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * The configuration path of a test field
     *
     * @param string $fieldId
     * @return string
     */
    protected static function path(string $fieldId): string
    {
        return self::SECTION . '/' . self::GROUP . '/' . $fieldId;
    }

    /**
     * @return void
     */
    private function dropObjectsBuiltFromStructure(): void
    {
        foreach (self::BUILT_FROM_STRUCTURE as $className) {
            $this->objectManager()->removeSharedInstance($className);
        }
    }

    /**
     * Make a loader of the areas' declarations the one the container hands out, under its class and its interface
     *
     * @param ConfigLoaderInterface $declarations
     * @return void
     */
    private function useDeclarations(ConfigLoaderInterface $declarations): void
    {
        $objectManager = $this->objectManager();
        foreach (self::BUILT_FROM_DECLARATIONS as $className) {
            $objectManager->removeSharedInstance($className);
        }
        $objectManager->addSharedInstance($declarations, ConfigLoader::class);
        $objectManager->addSharedInstance($declarations, ConfigLoaderInterface::class);
    }

    /**
     * The configuration structure data holding only the test section while the scope is the admin area's
     *
     * @param array<string, array<string, string>> $fields
     * @param ScopeInterface $scope The configuration scope the data is read for
     * @return StructureData
     */
    private function structureData(array $fields, ScopeInterface $scope): StructureData
    {
        $children = [];
        foreach ($fields as $fieldId => $definition) {
            $children[$fieldId] = $definition + [
                'id' => $fieldId,
                'type' => 'text',
                'showInDefault' => '1',
                '_elementType' => 'field',
                'path' => self::SECTION . '/' . self::GROUP,
            ];
        }
        $sections = [
            'sections' => [
                self::SECTION => [
                    'id' => self::SECTION,
                    'showInDefault' => '1',
                    'label' => 'Configuration fields test',
                    '_elementType' => 'section',
                    'children' => [
                        self::GROUP => [
                            'id' => self::GROUP,
                            'showInDefault' => '1',
                            'label' => 'Fields',
                            '_elementType' => 'group',
                            'path' => self::SECTION,
                            'children' => $children,
                        ],
                    ],
                ],
            ],
        ];

        return new class ($sections, $scope) extends StructureData {
            /**
             * @param array<string, mixed> $sections
             * @param ScopeInterface $scope
             */
            public function __construct(
                private readonly array $sections,
                private readonly ScopeInterface $scope
            ) {
            }

            /**
             * @param string|null $path
             * @param mixed $default
             * @return mixed
             */
            public function get($path = null, $default = null)
            {
                if ($path !== null) {
                    return $default;
                }

                return $this->scope->getCurrentScope() === Area::AREA_ADMINHTML ? $this->sections : [];
            }
        };
    }

    /**
     * @param string $fieldId
     * @return list<mixed>
     */
    private function storedValues(string $fieldId): array
    {
        $connection = $this->resource()->getConnection();

        return array_values($connection->fetchCol(
            $connection->select()
                ->from($this->resource()->getTableName('core_config_data'), ['value'])
                ->where('path = ?', self::path($fieldId))
                ->where('scope = ?', 'default')
        ));
    }

    /**
     * @return void
     */
    private function deleteStoredValues(): void
    {
        $connection = $this->resource()->getConnection();
        $connection->delete(
            $this->resource()->getTableName('core_config_data'),
            ['path LIKE ?' => self::SECTION . '/%']
        );
    }

    /**
     * @return ResourceConnection
     */
    private function resource(): ResourceConnection
    {
        $resource = $this->objectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);

        return $resource;
    }

    /**
     * @return ObjectManager
     */
    private function objectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
