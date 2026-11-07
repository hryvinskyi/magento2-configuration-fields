<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\SettingsFactoryFake;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A field's tagListConfig read into typed settings, with the defaults for every key left out
 */
class SettingsReaderTest extends TestCase
{
    /**
     * The reader under test, over a factory that builds the settings directly
     *
     * @var SettingsReader
     */
    private SettingsReader $reader;

    protected function setUp(): void
    {
        $this->reader = new SettingsReader(new SettingsFactoryFake());
    }

    public function testDefaults(): void
    {
        $settings = $this->reader->read([]);

        self::assertSame('Type value and press Enter...', $settings->placeholder());
        self::assertSame('^[a-zA-Z0-9.!#$%&\'*+\/=?^_`"{|}~-]+$', $settings->validation());
        self::assertSame(
            'Use letters, digits and . ! # $ % & \' * + / = ? ^ _ ` " { | } ~ - only, without spaces.',
            $settings->validationMessage()
        );
        self::assertTrue($settings->uppercase());
        self::assertSame("\n", $settings->separator());
        self::assertSame('separator', $settings->saveType());
    }

    public function testConfiguredValues(): void
    {
        $settings = $this->reader->read([
            'placeholder' => 'Add a postcode',
            'validation' => '^[A-Z0-9 ]+$',
            'validation_message' => 'Letters, digits and spaces only',
            'uppercase' => false,
            'separator' => ',',
            'save_type' => 'json',
        ]);

        self::assertSame('Add a postcode', $settings->placeholder());
        self::assertSame('^[A-Z0-9 ]+$', $settings->validation());
        self::assertSame('Letters, digits and spaces only', $settings->validationMessage());
        self::assertFalse($settings->uppercase());
        self::assertSame(',', $settings->separator());
        self::assertSame('json', $settings->saveType());
    }

    /**
     * @param array<string, string> $tagListConfig
     * @return void
     */
    #[DataProvider('ownPatternsWithoutAMessage')]
    public function testAnOwnPatternWithoutItsOwnMessageGetsTheNeutralMessage(array $tagListConfig): void
    {
        self::assertSame(
            'This value does not match the format this field accepts.',
            $this->reader->read($tagListConfig)->validationMessage()
        );
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function ownPatternsWithoutAMessage(): array
    {
        return [
            'no message' => [['validation' => '^[A-Za-z0-9_*/]+$']],
            'an empty message' => [['validation' => '^[A-Za-z0-9_*/]+$', 'validation_message' => '']],
        ];
    }

    public function testTheDefaultPatternSpelledOutKeepsTheMessageListingItsCharacters(): void
    {
        $settings = $this->reader->read(['validation' => '^[a-zA-Z0-9.!#$%&\'*+\/=?^_`"{|}~-]+$']);

        self::assertStringStartsWith('Use letters, digits and', $settings->validationMessage());
    }

    public function testTranslatablePhrasesAreRenderedToText(): void
    {
        $settings = $this->reader->read([
            'placeholder' => new Phrase('Add a route'),
            'validation_message' => new Phrase('Use %1 only', ['letters']),
        ]);

        self::assertSame('Add a route', $settings->placeholder());
        self::assertSame('Use letters only', $settings->validationMessage());
    }

    /**
     * @param string $configured
     * @param string $expected
     * @return void
     */
    #[DataProvider('separatorSpellings')]
    public function testSeparatorSpellingsBecomeTheirCharacters(string $configured, string $expected): void
    {
        self::assertSame($expected, $this->reader->read(['separator' => $configured])->separator());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function separatorSpellings(): array
    {
        return [
            'newline' => ['\n', "\n"],
            'tab' => ['\t', "\t"],
            'carriage return and newline' => ['\r\n', "\r\n"],
            'real newline' => ["\n", "\n"],
            'comma' => [',', ','],
            'pipe with spaces' => [' | ', ' | '],
        ];
    }

    public function testTheStringFalseStillMeansUppercase(): void
    {
        self::assertTrue($this->reader->read(['uppercase' => 'false'])->uppercase());
        self::assertFalse($this->reader->read(['uppercase' => '0'])->uppercase());
    }

    public function testAnEmptySeparatorIsRefused(): void
    {
        $this->expectException(LocalizedException::class);

        $this->reader->read(['separator' => '']);
    }
}
