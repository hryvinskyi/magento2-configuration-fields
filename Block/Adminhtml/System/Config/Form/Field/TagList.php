<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\ViewModel\TagListField;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Tag List configuration field
 *
 * Renders a tag-style input for managing lists of values (e.g., postcodes, SKUs, keywords).
 * Users can add tags by typing and pressing Enter, remove tags with × button,
 * and paste multiple values at once.
 *
 * Usage in system.xml with di.xml configuration:
 *
 * 1. Create virtual type in di.xml:
 * <virtualType name="MyModule\Block\Adminhtml\MyTagList"
 *              type="Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList">
 *     <arguments>
 *         <argument name="tagListConfig" xsi:type="array">
 *             <item name="placeholder" xsi:type="string">Enter value...</item>
 *             <item name="validation" xsi:type="string">^[A-Z0-9%]+$</item>
 *             <item name="uppercase" xsi:type="boolean">true</item>
 *             <item name="separator" xsi:type="string">\n</item>
 *             <item name="save_type" xsi:type="string">json</item>
 *         </argument>
 *     </arguments>
 * </virtualType>
 *
 * 2. Reference in system.xml:
 * <field id="my_field" translate="label" type="text" sortOrder="10" showInDefault="1">
 *     <label>My Tag List</label>
 *     <frontend_model>MyModule\Block\Adminhtml\MyTagList</frontend_model>
 *     <backend_model>MyModule\Model\Config\Backend\MyTagList</backend_model>
 * </field>
 *
 * Configuration options:
 * - placeholder: Custom placeholder text (default: "Type value and press Enter...")
 * - validation: Regex pattern for validation (default: ^[a-zA-Z0-9.!#$%&'*+\/=?^_`"{|}~-]+$, letters, digits and
 *   . ! # $ % & ' * + / = ? ^ _ ` " { | } ~ - with no spaces)
 * - validation_message: Message shown for a value that does not match (default: "Use letters, digits and
 *   . ! # $ % & ' * + / = ? ^ _ ` " { | } ~ - only, without spaces.")
 * - uppercase: Whether to auto-uppercase values (default: true)
 * - separator: Separator for stored values (default: newline)
 * - save_type: Storage format - 'json', 'serialized', or 'separator' (default: separator)
 *
 * The block's declared `tagListConfig` argument owns the field's settings: the browser gets them through the view
 * model, and the backend model reads the same declared argument from the admin area's container declarations when the
 * field is saved, so the pattern a value is checked against has one owner. A subclass that changes the argument in
 * code changes what the browser shows, not what the save checks.
 *
 * The view model and the label wrapper arrive as items of the block's `data` argument, declared for this type in the
 * module's adminhtml `di.xml`; Magento merges a type's arguments into its subclasses and virtual types, so a subclass
 * that declares its own constructor must forward `$data` to this one.
 */
class TagList extends AbstractWidgetField
{
    public const VIEW_MODEL = 'tag_list_view_model';

    /**
     * @param Context $context
     * @param array<mixed> $tagListConfig Configuration for tag list behavior
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly array $tagListConfig = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The tag-list widget
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When the view model is not configured
     */
    protected function widgetHtml(AbstractElement $element): string
    {
        return $this->renderWidget('Hryvinskyi_ConfigurationFields::system/config/tag_list.phtml', [
            'element' => $element,
            'view_model' => $this->requiredData(self::VIEW_MODEL, TagListField::class),
            'tag_list_config' => $this->tagListConfig,
        ]);
    }
}
