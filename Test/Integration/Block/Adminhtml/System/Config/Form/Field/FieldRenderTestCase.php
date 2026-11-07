<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Magento\Config\Block\System\Config\Form\Field as MagentoField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Factory as ElementFactory;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Renders a configuration field block the way Magento's configuration form does: the block is built by the
 * container and placed on the real layout, the element comes from Magento's form element factory, attached to a form
 * whose html ids carry a prefix
 */
abstract class FieldRenderTestCase extends TestCase
{
    /**
     * An html id with characters that must be escaped in an attribute and that no CSS selector accepts as written
     */
    protected const HOSTILE_HTML_ID = 'field"<x>';

    protected const HTML_ID_PREFIX = 'cfg_';

    /**
     * The field's html id as the browser reads it from the rendered page
     */
    protected const HTML_ID = self::HTML_ID_PREFIX . self::HOSTILE_HTML_ID;

    protected const NAME = 'groups[g][fields][f][value]';

    /**
     * The whole row Magento's configuration form prints for the field
     *
     * @param string $blockType A block class or virtual type
     * @param array<string,mixed> $elementData
     * @return string
     */
    protected function renderRow(string $blockType, array $elementData): string
    {
        return $this->renderElement($blockType, $this->element($elementData));
    }

    /**
     * The whole row Magento's configuration form prints for this element
     *
     * @param string $blockType A block class or virtual type
     * @param AbstractElement $element
     * @return string
     */
    protected function renderElement(string $blockType, AbstractElement $element): string
    {
        $objectManager = Bootstrap::getObjectManager();
        $layout = $objectManager->get(LayoutInterface::class);
        self::assertInstanceOf(LayoutInterface::class, $layout);
        $block = $objectManager->create($blockType);
        self::assertInstanceOf(MagentoField::class, $block);
        $block->setLayout($layout);

        return $block->render($element);
    }

    /**
     * The widget's root id for the field's html id, as the module derives it
     *
     * @return string
     */
    protected function rootId(): string
    {
        $identity = Bootstrap::getObjectManager()->get(ElementIdentity::class);
        self::assertInstanceOf(ElementIdentity::class, $identity);

        return $identity->rootId($this->element([]));
    }

    /**
     * An element of the given type on a form, with the hostile html id and the field's name unless the data names
     * others
     *
     * @param array<string,mixed> $data
     * @param string $type An element type Magento's form element factory knows, `text` unless named
     * @return AbstractElement
     */
    protected function element(array $data, string $type = 'text'): AbstractElement
    {
        $objectManager = Bootstrap::getObjectManager();
        $formFactory = $objectManager->get(FormFactory::class);
        $elementFactory = $objectManager->get(ElementFactory::class);
        self::assertInstanceOf(FormFactory::class, $formFactory);
        self::assertInstanceOf(ElementFactory::class, $elementFactory);

        $form = $formFactory->create();
        $form->setData('html_id_prefix', self::HTML_ID_PREFIX);
        $element = $elementFactory->create(
            $type,
            ['data' => $data + ['html_id' => self::HOSTILE_HTML_ID, 'name' => self::NAME, 'label' => 'Field']]
        );
        $element->setForm($form);

        return $element;
    }

    /**
     * The rendered HTML as a document to query
     *
     * @param string $html
     * @return \DOMXPath
     */
    protected function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><table>' . $html . '</table></body></html>'
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    /**
     * The single element a query finds
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @return \DOMElement
     */
    protected function one(\DOMXPath $xpath, string $query): \DOMElement
    {
        $nodes = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        self::assertSame(1, $nodes->length, 'Expected exactly one match for ' . $query);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        return $node;
    }

    /**
     * The element carrying a data-role
     *
     * @param \DOMXPath $xpath
     * @param string $role
     * @return \DOMElement
     */
    protected function role(\DOMXPath $xpath, string $role): \DOMElement
    {
        return $this->one($xpath, '//*[@data-role="' . $role . '"]');
    }

    /**
     * The x-magento-init object printed after the widget, decoded
     *
     * @param \DOMXPath $xpath
     * @return array<mixed>
     */
    protected function init(\DOMXPath $xpath): array
    {
        $json = $this->one($xpath, '//script[@type="text/x-magento-init"]')->textContent;
        self::assertStringNotContainsString('<', $json, 'Tags in the init JSON are hex-escaped');
        $init = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($init);

        return $init;
    }

    /**
     * The config the component receives from the x-magento-init object, which mounts it on the widget's root
     *
     * @param \DOMXPath $xpath
     * @param string $rootId
     * @param string $component
     * @return array<mixed>
     */
    protected function componentConfig(\DOMXPath $xpath, string $rootId, string $component): array
    {
        $init = $this->init($xpath);
        self::assertSame(['#' . $rootId], array_keys($init));
        $byComponent = $init['#' . $rootId];
        self::assertIsArray($byComponent);
        self::assertSame([$component], array_keys($byComponent));
        $config = $byComponent[$component];
        self::assertIsArray($config);

        return $config;
    }

    /**
     * The class names an element carries
     *
     * @param \DOMElement $element
     * @return list<string>
     */
    protected function classes(\DOMElement $element): array
    {
        return array_values(array_filter(explode(' ', $element->getAttribute('class'))));
    }
}
