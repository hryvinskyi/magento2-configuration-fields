<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;

/**
 * Places a field widget between the HTML a field declares before and after its element, as Magento's own form
 * elements do
 *
 * The before and after HTML is printed as given: a field's author writes HTML there. The label that wraps it points
 * at the field's element, and that id is escaped.
 */
class AddonLabelWrapper
{
    /**
     * @param ElementIdentity $identity
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * The widget with the field's before-element label, after-element script and after-element label around it
     *
     * Every widget field renders its markup through this method, so another module that adds markup after a widget
     * field (an after plugin on this method) reaches all of them; its signature and the fact that the returned HTML
     * ends with the after-element label are kept across minor releases.
     *
     * @api
     * @param AbstractElement $element
     * @param string $widgetHtml
     * @return string
     */
    public function wrap(AbstractElement $element, string $widgetHtml): string
    {
        $for = $this->escaper->escapeHtmlAttr($this->identity->htmlId($element));
        $html = '';

        $before = $this->html($element->getBeforeElementHtml());
        if ($before !== '') {
            $html .= '<label class="addbefore" for="' . $for . '">' . $before . '</label>';
        }

        $html .= $widgetHtml;
        $html .= $this->html($element->getAfterElementJs());

        $after = $this->html($element->getAfterElementHtml());
        if ($after !== '') {
            $html .= '<label class="addafter" for="' . $for . '">' . $after . '</label>';
        }

        return $html;
    }

    /**
     * HTML an element holds, or an empty string when it holds none
     *
     * @param mixed $html
     * @return string
     */
    private function html(mixed $html): string
    {
        if (is_scalar($html) || $html instanceof \Stringable) {
            return (string) $html;
        }

        return '';
    }
}
