<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * The tag-list storage formats, by save type
 *
 * The formats this module ships are registered under the save types below. Another module registers its own by
 * adding an item, keyed by the save type, to the `codecs` argument of the pool's implementation in `di.xml`.
 *
 * @api
 */
interface TagListCodecPoolInterface
{
    /**
     * A JSON array of strings
     */
    public const SAVE_TYPE_JSON = 'json';

    /**
     * A PHP-serialized list of strings
     */
    public const SAVE_TYPE_SERIALIZED = 'serialized';

    /**
     * The values joined by the field's separator; also the form in which the browser posts a tag list
     */
    public const SAVE_TYPE_SEPARATOR = 'separator';

    /**
     * The format registered under a save type
     *
     * @param string $saveType
     * @return TagListCodecInterface
     * @throws LocalizedException When no format is registered under the save type; the message names it and the
     *     save types that are registered
     */
    public function forSaveType(string $saveType): TagListCodecInterface;
}
