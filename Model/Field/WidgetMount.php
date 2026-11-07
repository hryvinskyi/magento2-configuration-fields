<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Where a field widget's script starts: the widget's root id and the `x-magento-init` object that mounts the script
 * on that root with its config
 *
 * The object is returned both as data and as JSON. The serializer is Magento's hex-tag JSON serializer, given in
 * the module's global `di.xml`, so it holds in every area: it escapes `<` and `>`, so a value such as `</script>` in
 * the config cannot end the script block it is printed into. Another serializer passed in its place must escape
 * them as well.
 */
class WidgetMount
{
    /**
     * @param ElementIdentity $identity
     * @param SerializerInterface $json A JSON serializer that escapes `<` and `>`
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * The mount of one widget
     *
     * @param AbstractElement $element The field the widget edits
     * @param string $component The script's module id
     * @param array<string,mixed> $componentConfig The config the script receives
     * @return array{
     *     rootId: non-empty-string,
     *     init: array<non-empty-string, array<string, array<string, mixed>>>,
     *     initJson: string
     * }
     * @throws \InvalidArgumentException When the config cannot be written as JSON
     */
    public function mount(AbstractElement $element, string $component, array $componentConfig): array
    {
        $rootId = $this->identity->rootId($element);
        $init = ['#' . $rootId => [$component => $componentConfig]];
        $initJson = $this->json->serialize($init);
        if (!is_string($initJson)) {
            throw new \InvalidArgumentException(
                sprintf('The config of the widget "%s" could not be written as JSON.', $component)
            );
        }

        return [
            'rootId' => $rootId,
            'init' => $init,
            'initJson' => $initJson,
        ];
    }
}
