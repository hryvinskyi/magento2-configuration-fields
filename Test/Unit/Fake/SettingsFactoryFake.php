<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Fake;

use Hryvinskyi\ConfigurationFields\Model\TagList\Settings;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsFactory;

/**
 * Builds Settings from the named constructor arguments, as the generated factory does through the object manager
 */
class SettingsFactoryFake extends SettingsFactory
{
    /**
     * No object manager: the settings are built directly
     */
    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return Settings
     */
    public function create(array $data = [])
    {
        return new Settings(
            $this->text($data, 'placeholder'),
            $this->text($data, 'validation'),
            $this->text($data, 'validationMessage'),
            ($data['uppercase'] ?? null) === true,
            $this->text($data, 'separator'),
            $this->text($data, 'saveType')
        );
    }

    /**
     * A text argument, which the reader always passes
     *
     * @param array<string, mixed> $data
     * @param string $name
     * @return string
     */
    private function text(array $data, string $name): string
    {
        $value = $data[$name] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Settings argument "%s" must be a string.', $name));
        }

        return $value;
    }
}
