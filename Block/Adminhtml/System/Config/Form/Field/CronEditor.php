<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field as MagentoField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Template;

class CronEditor extends MagentoField
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '';
        $htmlId = $element->getHtmlId();

        $beforeElementHtml = $element->getBeforeElementHtml();
        if ($beforeElementHtml) {
            $html .= '<label class="addbefore" for="' . $htmlId . '">' . $beforeElementHtml . '</label>';
        }

        $html .= $this->getLayout()->createBlock(Template::class)
            ->setTemplate('Hryvinskyi_ConfigurationFields::system/config/cron_editor.phtml')
            ->setData('element', $element)
            ->toHtml();

        $afterElementJs = $element->getAfterElementJs();
        if ($afterElementJs) {
            $html .= $afterElementJs;
        }

        $afterElementHtml = $element->getAfterElementHtml();
        if ($afterElementHtml) {
            $html .= '<label class="addafter" for="' . $htmlId . '">' . $afterElementHtml . '</label>';
        }

        return $html;
    }
}

