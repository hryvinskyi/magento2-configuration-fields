<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\JavaScriptPattern;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueCheck;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\SettingsFactoryFake;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * A tag list's values checked against the field's pattern on the server, as the browser checks them
 */
class ValueCheckTest extends TestCase
{
    /**
     * @var ValueCheck
     */
    private ValueCheck $check;

    /**
     * @var SettingsReader
     */
    private SettingsReader $reader;

    protected function setUp(): void
    {
        $this->check = new ValueCheck(new JavaScriptPattern());
        $this->reader = new SettingsReader(new SettingsFactoryFake());
    }

    public function testAcceptsValuesThatMatch(): void
    {
        $this->expectNotToPerformAssertions();

        $this->check->assertAccepted($this->reader->read(['validation' => '^[a-z/]+$', 'uppercase' => false]), [
            'catalog/product',
            'sales',
        ], 'Ignored routes');
    }

    public function testRefusesTheFirstValueThatDoesNotMatchNamingTheFieldTheValueAndTheFieldsMessage(): void
    {
        $settings = $this->reader->read([
            'validation' => '^[0-9.]+$',
            'validation_message' => 'Enter an IPv4 address.',
            'uppercase' => false,
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'The field "Excluded IPs" cannot be saved with the value "localhost": Enter an IPv4 address.'
        );

        $this->check->assertAccepted($settings, ['10.0.0.1', 'localhost', 'other'], 'Excluded IPs');
    }

    public function testUpperCasesBeforeMatchingWhenTheFieldAsksForIt(): void
    {
        $this->expectNotToPerformAssertions();

        $this->check->assertAccepted(
            $this->reader->read(['validation' => '^[A-Z0-9 ]+$', 'uppercase' => true]),
            ['sw1a 1aa', 'straße'],
            'Postcodes'
        );
    }

    public function testDoesNotUpperCaseWhenTheFieldDoesNotAskForIt(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('the value "sw1a 1aa"');

        $this->check->assertAccepted(
            $this->reader->read(['validation' => '^[A-Z0-9 ]+$', 'uppercase' => false]),
            ['sw1a 1aa'],
            'Postcodes'
        );
    }

    public function testAPatternThatCannotBeCompiledRefusesTheSaveNamingTheField(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'The field "Codes" cannot be saved: its validation pattern is not a regular expression the server can'
            . ' check.'
        );

        $this->check->assertAccepted($this->reader->read(['validation' => '^[A-Z+$']), ['A'], 'Codes');
    }

    public function testAnEmptyListIsAcceptedWhateverThePattern(): void
    {
        $this->expectNotToPerformAssertions();

        $this->check->assertAccepted($this->reader->read(['validation' => '^[A-Z+$']), [], 'Codes');
    }

    public function testAFailedCompilationLeavesNoErrorHandlerBehind(): void
    {
        $marker = static fn (): bool => false;
        set_error_handler($marker);
        try {
            $refused = false;
            try {
                $this->check->assertAccepted($this->reader->read(['validation' => '(']), ['A'], 'Codes');
            } catch (LocalizedException) {
                $refused = true;
            }
            $current = set_error_handler(static fn (): bool => false);
            restore_error_handler();

            self::assertTrue($refused);
            self::assertSame($marker, $current);
        } finally {
            restore_error_handler();
        }
    }
}
