<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Fake;

use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\Math\Random;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Builds a real form element attached to a form, as Magento's configuration form hands one to a field block
 *
 * The element escapes its html id and its name with Magento's own escaper, so a test sees what a field sees.
 */
abstract class FormElementTestCase extends TestCase
{
    /**
     * A form element with the given data, on a form whose html ids carry the given prefix
     *
     * @param array<string,mixed> $data
     * @param string $htmlIdPrefix
     * @return AbstractElement
     */
    protected function element(array $data, string $htmlIdPrefix = ''): AbstractElement
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $element = new class (
            new Factory($objectManager),
            new CollectionFactory($objectManager),
            new Escaper(),
            $data,
            $this->createMock(SecureHtmlRenderer::class),
            $this->createMock(Random::class)
        ) extends AbstractElement {
        };
        $element->setForm(new Form(
            new Factory($objectManager),
            new CollectionFactory($objectManager),
            $this->createMock(FormKey::class),
            ['html_id_prefix' => $htmlIdPrefix]
        ));

        return $element;
    }
}
