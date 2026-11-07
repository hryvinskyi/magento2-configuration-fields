<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList\FieldBlock;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList as TagListField;
use Hryvinskyi\ConfigurationFields\Model\TagList\FieldBlockConfigInterface;
use Magento\Framework\App\Area;
use Magento\Framework\ObjectManager\Config\ConfigFactory;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

/**
 * The `tagListConfig` of a field block as the admin area's container declarations give it, read without applying
 * them to the running container
 *
 * It answers the same way in every area, a request to the admin included: frontend models exist only in the admin,
 * consumers declare their field blocks' virtual types in their admin-area `di.xml`, and the running container has
 * that configuration only in an admin request, so it is never asked. Applying the admin area's declarations to it
 * would leave the admin's preferences in force for the rest of a storefront or cron request. Only the declared
 * argument counts; no block is built, so a block class that changes its settings in code is not consulted.
 *
 * The declarations are the modules' `di.xml` files of the global scope and of the admin area, in that order, as an
 * admin request's container has them; they are read through a loader that reads those files (and caches what it
 * read) in every application mode, so they come in one shape whether or not the container is compiled. They are
 * merged into a container configuration of their own, built at the first save of a tag list in a process, which
 * resolves the frontend model's virtual types and merges its arguments exactly as the container does: a virtual
 * type's `tagListConfig` over the one of the type it is declared on, recursively. That configuration only answers
 * questions; no object is built from it, and the running container never sees it. The application's primary
 * declarations (`app/etc/di.xml`) are not read: they declare no field block.
 *
 * The argument is returned as declared: items that are not text or a flag (an object, an init parameter) are left
 * for the settings reader, which ignores them.
 */
class DeclaredFieldBlockConfig implements FieldBlockConfigInterface
{
    private const ARGUMENT = 'tagListConfig';

    /**
     * @var ObjectManagerConfig|null
     */
    private ?ObjectManagerConfig $adminConfig = null;

    /**
     * @param ConfigLoaderInterface $declarations Reads an area's container declarations from the `di.xml` files
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        private readonly ConfigLoaderInterface $declarations,
        private readonly ConfigFactory $configFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function tagListConfig(string $frontendModel): ?array
    {
        $config = $this->adminConfig();
        $instanceType = $config->getInstanceType($frontendModel);
        if (!is_string($instanceType) || !is_a($instanceType, TagListField::class, true)) {
            return null;
        }
        $arguments = $config->getArguments($frontendModel);
        $tagListConfig = is_array($arguments) ? ($arguments[self::ARGUMENT] ?? []) : [];

        return is_array($tagListConfig) ? $tagListConfig : [];
    }

    /**
     * The admin area's container configuration, merged from the declarations once
     *
     * @return ObjectManagerConfig
     */
    private function adminConfig(): ObjectManagerConfig
    {
        if ($this->adminConfig === null) {
            $config = $this->configFactory->create();
            foreach ([Area::AREA_GLOBAL, Area::AREA_ADMINHTML] as $area) {
                $config->extend($this->declarations->load($area));
            }
            $this->adminConfig = $config;
        }

        return $this->adminConfig;
    }
}
