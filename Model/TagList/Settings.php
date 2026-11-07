<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Magento\Framework\Exception\LocalizedException;

/**
 * How one tag-list field behaves and stores its values, read from its `tagListConfig`
 *
 * Built only by SettingsReader, through the generated factory. The separator is the real character sequence (a
 * configured `\n`, `\t` or `\r` spelling is already turned into the character) and is never empty.
 */
class Settings
{
    /**
     * @var non-empty-string
     */
    private readonly string $separator;

    /**
     * @param string $placeholder Text shown in the empty input
     * @param string $validation Regular expression, without delimiters, each value must match in the browser
     * @param string $validationMessage Message shown when a value does not match the pattern
     * @param bool $uppercase Whether values are upper-cased as they are typed
     * @param string $separator Character sequence that joins the values the browser posts
     * @param string $saveType Save type under which the storage format is registered in the codec pool
     * @throws LocalizedException When the separator is empty
     */
    public function __construct(
        private readonly string $placeholder,
        private readonly string $validation,
        private readonly string $validationMessage,
        private readonly bool $uppercase,
        string $separator,
        private readonly string $saveType
    ) {
        if ($separator === '') {
            throw new LocalizedException(__('A tag list field needs a separator that is not empty.'));
        }
        $this->separator = $separator;
    }

    /**
     * Text shown in the empty input
     *
     * @return string
     */
    public function placeholder(): string
    {
        return $this->placeholder;
    }

    /**
     * Regular expression, without delimiters, each value must match in the browser
     *
     * @return string
     */
    public function validation(): string
    {
        return $this->validation;
    }

    /**
     * Message shown when a value does not match the pattern
     *
     * @return string
     */
    public function validationMessage(): string
    {
        return $this->validationMessage;
    }

    /**
     * Whether values are upper-cased as they are typed
     *
     * @return bool
     */
    public function uppercase(): bool
    {
        return $this->uppercase;
    }

    /**
     * Character sequence that joins the values the browser posts
     *
     * @return non-empty-string
     */
    public function separator(): string
    {
        return $this->separator;
    }

    /**
     * Save type under which the storage format is registered in the codec pool
     *
     * @return string
     */
    public function saveType(): string
    {
        return $this->saveType;
    }
}
