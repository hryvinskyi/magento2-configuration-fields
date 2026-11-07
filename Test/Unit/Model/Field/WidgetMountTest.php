<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The x-magento-init object that mounts a widget's script on its root, and its JSON safe to print in a script block
 */
class WidgetMountTest extends FormElementTestCase
{
    public function testMountsTheComponentOnTheRootWithItsConfigAndHexEscapesTags(): void
    {
        $identity = new ElementIdentity();
        $element = $this->element(['html_id' => 'field"<id>']);
        $mount = (new WidgetMount($identity, new JsonHexTag()))
            ->mount($element, 'Vendor_Module/js/widget', ['value' => '</script><b>']);

        $rootId = $identity->rootId($element);
        self::assertSame($rootId, $mount['rootId']);
        self::assertSame(
            ['#' . $rootId => ['Vendor_Module/js/widget' => ['value' => '</script><b>']]],
            $mount['init']
        );
        self::assertStringNotContainsString('<', $mount['initJson']);
        self::assertStringNotContainsString('>', $mount['initJson']);
        $hexEscapedEnd = trim((string) json_encode('</script>', JSON_HEX_TAG), '"');
        self::assertStringContainsString($hexEscapedEnd, $mount['initJson']);
        self::assertSame($mount['init'], json_decode($mount['initJson'], true));
    }

    public function testAConfigTheSerializerCannotWriteStopsTheRender(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vendor_Module/js/widget');

        (new WidgetMount(new ElementIdentity(), $serializer))
            ->mount($this->element(['html_id' => 'f']), 'Vendor_Module/js/widget', []);
    }
}
