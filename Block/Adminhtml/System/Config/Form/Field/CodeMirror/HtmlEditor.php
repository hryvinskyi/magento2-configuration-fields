<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CodeMirror;

use Magento\Backend\Block\Template\Context;

/**
 * HTML CodeMirror editor field for system configuration.
 * Supports HTML with embedded CSS and JavaScript (htmlmixed mode).
 */
class HtmlEditor extends Editor
{
    /**
     * @param Context $context
     * @param array<string,mixed> $editorConfig
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        array $editorConfig = [],
        array $data = []
    ) {
        $editorConfig = array_merge(['mode' => 'htmlmixed'], $editorConfig);
        parent::__construct($context, $editorConfig, $data);
    }
}
