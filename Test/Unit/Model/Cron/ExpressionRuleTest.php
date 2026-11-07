<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Cron;

use Hryvinskyi\ConfigurationFields\Model\Cron\ExpressionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The server's cron rule decides as the editor's browser rule does: the examples are the ones the browser rule's
 * Node tests (`Test/Js/cron-fields.test.cjs`) use, copied as values, so a change to one rule fails the other's suite
 * until both agree again
 */
class ExpressionRuleTest extends TestCase
{
    /**
     * @var ExpressionRule
     */
    private ExpressionRule $rule;

    protected function setUp(): void
    {
        $this->rule = new ExpressionRule();
    }

    /**
     * @param string $part
     * @return void
     */
    #[DataProvider('acceptedParts')]
    public function testAcceptsAPartMagentosSchedulerRuns(string $part): void
    {
        self::assertTrue($this->rule->isValidPart($part));
    }

    /**
     * Magento's own Schedule::matchCronExpression() examples, month and weekday names, steps on ranges, comma lists
     * of ranges, and numbers outside the calendar
     *
     * @return array<string, array{string}>
     */
    public static function acceptedParts(): array
    {
        $parts = [
            '*', '0,1,20', '0,1,22', '0,1,*', '0-20', '*/2', '*/20', '0-20/5', '3-20/5',
            '1/5', '5/5', '10/5', '4/5', '8/5', '13/5',
            'JAN', 'DEC', 'JAN-DEC', 'SUN', 'SAT', 'mon', 'jan', 'Mon-Fri', 'january-june/2', 'monday',
            '1-30/5', '0-5,10-59/5', '2-10,15-25',
            '61', '24', '7',
            '99', '007', '00099', '*/99', '0-99/99', '*/007',
        ];

        return array_combine(
            array_map(static fn (string $part): string => '"' . $part . '"', $parts),
            array_map(static fn (string $part): array => [$part], $parts)
        );
    }

    /**
     * @param string $part
     * @return void
     */
    #[DataProvider('refusedParts')]
    public function testRefusesAPartMagentosSchedulerFailsOn(string $part): void
    {
        self::assertFalse($this->rule->isValidPart($part));
    }

    /**
     * What Magento's scheduler fails on, letters its setCronExpr() keeps although matching them fails, a word that
     * names no month or weekday, an empty list entry, a step of zero in every spelling, and a number or a step of
     * more than two digits, which no schedule needs and which, past an integer's range, makes the scheduler fail
     *
     * @return array<string, array{string}>
     */
    public static function refusedParts(): array
    {
        $parts = [
            '1/2/3', '1/', '-', '1-2-3', '0/0', '*/00', '?', 'L', 'W', 'C', ',', '1,', 'a', 'mo', 'abc', '5-', '',
            '*/0', '5/0', '1-5/0', 'jan-dec/0', '1,*/0',
            '+5', '1.5', '1e2', '/5',
            '100', '0100', '9223372036854775807', '9223372036854775808', '99999999999999999999',
            '1-100', '100-1', '*/100', '1-5/100', '*/99999999999999999999', '1,99999999999999999999',
        ];

        return array_combine(
            array_map(static fn (string $part): string => '"' . $part . '"', $parts),
            array_map(static fn (string $part): array => [$part], $parts)
        );
    }

    /**
     * @param string $expression
     * @param bool $valid
     * @return void
     */
    #[DataProvider('expressions')]
    public function testAnExpressionIsEmptyOrFiveValidPartsWithOrWithoutASixth(string $expression, bool $valid): void
    {
        self::assertSame($valid, $this->rule->isValidExpression($expression));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function expressions(): array
    {
        return [
            'empty' => ['', true],
            'only whitespace' => [" \t ", true],
            'every five minutes' => ['*/5 * * * *', true],
            'a weekday by name' => ['0 2 * * mon', true],
            'Magento\'s own documented example' => ['* 0-5,10-59/5 2-10,15-25 january-june/2 mon-fri', true],
            'six parts' => ['1 2 3 4 5 6', true],
            'a sixth part that is a star' => ['0 0 * * * *', true],
            'a sixth part that is never read' => ['0 0 * * * whatever', true],
            'a minute outside the calendar' => ['99 * * * *', true],
            'surrounded and split by the scheduler\'s whitespace' => ["\x0B0\f0\r*\n*\t* ", true],
            'four parts' => ['*/5 * * *', false],
            'seven parts' => ['1 2 3 4 5 6 7', false],
            'letters that name nothing' => ['a b c d e', false],
            'a letter the scheduler cannot match' => ['* * * L *', false],
            'a step of zero' => ['*/0 * * * *', false],
            'a step of zero on a range' => ['* * * * 1-5/0', false],
            'a step of one on a range' => ['*/10 * * * 1-5/1', true],
            'a minute too large for an integer' => ['99999999999999999999 * * * *', false],
            'a step too large for an integer' => ['*/99999999999999999999 * * * *', false],
            'a three-digit step' => ['*/100 * * * *', false],
            'the largest two-digit numbers' => ['99 99 99 99 99', true],
            'a no-break space inside a part' => ["0\u{00A0}0 * * * *", false],
            'a leading no-break space' => ["\u{00A0}0 0 * * *", false],
        ];
    }
}
