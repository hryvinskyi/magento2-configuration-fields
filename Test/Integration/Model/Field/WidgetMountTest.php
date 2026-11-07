<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Text;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The widget mount as the container builds it outside the admin area: its JSON still escapes the characters that
 * would end the script block it is printed into
 */
class WidgetMountTest extends TestCase
{
    public function testTheMountJsonEscapesTagsInTheGlobalArea(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $element = $objectManager->create(Text::class);
        self::assertInstanceOf(Text::class, $element);
        $element->setData('html_id', 'field');
        $form = $objectManager->create(Form::class);
        self::assertInstanceOf(Form::class, $form);
        $element->setForm($form);
        $mount = $objectManager->create(WidgetMount::class);
        self::assertInstanceOf(WidgetMount::class, $mount);

        $initJson = $mount->mount($element, 'Vendor_Module/js/widget', ['value' => '</script><b>'])['initJson'];

        self::assertStringNotContainsString('</script>', $initJson);
        self::assertStringNotContainsString('<b>', $initJson);
        self::assertStringContainsString('\u003C\/script\u003E', $initJson);
    }
}
