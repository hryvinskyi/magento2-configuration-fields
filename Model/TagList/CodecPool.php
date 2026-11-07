<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecInterface;
use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * The tag-list storage formats injected through `di.xml`, keyed by save type
 *
 * An unregistered save type fails loudly rather than falling back to another format, so a typo in a field's
 * `save_type` never stores values in a format its readers do not expect.
 */
class CodecPool implements TagListCodecPoolInterface
{
    /**
     * @param array<mixed> $codecs Storage formats keyed by save type; each must implement
     *     TagListCodecInterface
     */
    public function __construct(
        private readonly array $codecs = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forSaveType(string $saveType): TagListCodecInterface
    {
        $codec = $this->codecs[$saveType] ?? null;
        if (!$codec instanceof TagListCodecInterface) {
            throw new LocalizedException(
                __(
                    'The tag list save type "%1" is not registered. Registered save types: %2.',
                    $saveType,
                    implode(', ', $this->registeredSaveTypes())
                )
            );
        }

        return $codec;
    }

    /**
     * The save types under which a format is registered
     *
     * @return list<string>
     */
    private function registeredSaveTypes(): array
    {
        $saveTypes = [];
        foreach ($this->codecs as $saveType => $codec) {
            if ($codec instanceof TagListCodecInterface) {
                $saveTypes[] = (string) $saveType;
            }
        }

        return $saveTypes;
    }
}
