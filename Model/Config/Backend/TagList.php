<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Hryvinskyi\ConfigurationFields\Model\Field\SavedFieldName;
use Hryvinskyi\ConfigurationFields\Model\TagList\FieldSettingsSourceInterface;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueCheck;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model for a tag-list configuration field
 *
 * The browser posts the values joined by the field's separator. Before saving, they are split, trimmed, emptied
 * entries are dropped, and the list is written in the storage format the field's `save_type` names:
 * - json: a JSON array, ["value1","value2"]
 * - serialized: a PHP-serialized list, a:2:{i:0;s:6:"value1";...}
 * - separator (the default): the values joined by the separator
 * Another module adds a format by registering a codec in the codec pool. A save type no format is registered under
 * refuses the save with a message naming it, and nothing is stored. A posted value that is not valid UTF-8 refuses
 * the save in every format, so no such bytes reach the stored value and the page that prints it.
 *
 * A value stored in another format that the field could not read is posted back unchanged and re-encoded here as
 * values split by the separator, so the save that follows moves it into the field's format.
 *
 * Every value is checked against the field's `validation` pattern, as the browser checks it (ValueCheck): upper-cased
 * first when the field asks for it, matched, and stored as posted. The pattern, the case rule and the message are the
 * ones the field's frontend model shows in the browser, read from that block (FieldSettingsSourceInterface), so a
 * backend virtual type shared by several fields checks each by its own pattern; a field whose frontend model is not
 * a tag list is checked with this model's own `tagListConfig`. A value the pattern refuses, or a pattern the server
 * cannot compile, refuses the save with a message naming the field, and the value; a value stored before this check
 * existed therefore stops the save of its section until it is fixed. The storage format and the separator always come
 * from this model's own `tagListConfig`.
 *
 * Usage with di.xml:
 *
 * 1. Create virtual type in di.xml:
 * <virtualType name="MyModule\Model\Config\Backend\MyTagList"
 *              type="Hryvinskyi\ConfigurationFields\Model\Config\Backend\TagList">
 *     <arguments>
 *         <argument name="tagListConfig" xsi:type="array">
 *             <item name="separator" xsi:type="string">\n</item>
 *             <item name="save_type" xsi:type="string">json</item>
 *         </argument>
 *     </arguments>
 * </virtualType>
 *
 * 2. Reference in system.xml:
 * <backend_model>MyModule\Model\Config\Backend\MyTagList</backend_model>
 */
class TagList extends Value
{
    public const SAVE_TYPE_JSON = TagListCodecPoolInterface::SAVE_TYPE_JSON;
    public const SAVE_TYPE_SERIALIZED = TagListCodecPoolInterface::SAVE_TYPE_SERIALIZED;
    public const SAVE_TYPE_SEPARATOR = TagListCodecPoolInterface::SAVE_TYPE_SEPARATOR;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param TagListCodecPoolInterface $codecPool
     * @param SettingsReader $settingsReader
     * @param FieldSettingsSourceInterface $fieldSettings
     * @param ValueCheck $valueCheck
     * @param SavedFieldName $fieldName
     * @param array<mixed> $tagListConfig The field's settings; SettingsReader lists the keys
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly TagListCodecPoolInterface $codecPool,
        private readonly SettingsReader $settingsReader,
        private readonly FieldSettingsSourceInterface $fieldSettings,
        private readonly ValueCheck $valueCheck,
        private readonly SavedFieldName $fieldName,
        private readonly array $tagListConfig = [],
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Write the posted values in the field's storage format
     *
     * @return $this
     * @throws LocalizedException When the save type is not registered, the posted value is not valid UTF-8, the
     *     format registered for the posted form cannot read it, a value does not match the field's pattern or the
     *     pattern cannot be compiled, or the values cannot be written in the save type's format
     */
    public function beforeSave(): self
    {
        $settings = $this->settingsReader->read($this->tagListConfig);
        $storageCodec = $this->codecPool->forSaveType($settings->saveType());

        $posted = (string) $this->getValue();
        if ($posted === '') {
            return parent::beforeSave();
        }
        if (!mb_check_encoding($posted, 'UTF-8')) {
            throw new LocalizedException(
                __('A tag list value is not valid UTF-8 text, so the list cannot be saved.')
            );
        }

        $values = $this->codecPool
            ->forSaveType(TagListCodecPoolInterface::SAVE_TYPE_SEPARATOR)
            ->decode($posted, $settings->separator())
            ?? throw new LocalizedException(
                __('The posted tag list cannot be read as values joined by the separator, so it cannot be saved.')
            );
        $path = $this->getData('path');
        $this->valueCheck->assertAccepted(
            $this->fieldSettings->forPath(is_string($path) ? $path : '') ?? $settings,
            $values,
            $this->fieldName->of($this)
        );
        $this->setValue($storageCodec->encode($values, $settings->separator()));

        return parent::beforeSave();
    }
}
