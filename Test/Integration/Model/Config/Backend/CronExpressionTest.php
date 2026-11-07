<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Model\Config\Backend\CronExpression;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A cron field that opts in to the cron backend model, saved through Magento's config model: an expression Magento's
 * scheduler runs is stored as posted, any other stops the save of the whole section with a message naming the field
 */
#[AppArea('adminhtml')]
#[DbIsolation(false)]
class CronExpressionTest extends SectionSaveTestCase
{
    private const SCHEDULE = [
        'label' => 'Cleanup schedule',
        'backend_model' => CronExpression::class,
    ];

    /**
     * @param string $expression
     * @return void
     */
    #[DataProvider('runnableExpressions')]
    public function testStoresAnExpressionMagentosSchedulerRunsAsPosted(string $expression): void
    {
        $this->saveTestSection(['schedule' => self::SCHEDULE], ['schedule' => $expression]);

        self::assertTrue($this->isStored('schedule'));
        self::assertSame($expression, (string) $this->storedValue('schedule'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function runnableExpressions(): array
    {
        return [
            'empty' => [''],
            'every five minutes' => ['*/5 * * * *'],
            'names, ranges and steps on ranges' => ['* 0-5,10-59/5 2-10,15-25 january-june/2 mon-fri'],
            'a sixth part' => ['0 0 * * * 2026'],
        ];
    }

    /**
     * @param string $expression
     * @return void
     */
    #[DataProvider('refusedExpressions')]
    public function testRefusesAnExpressionNamingTheFieldAndTheValue(string $expression): void
    {
        try {
            $this->saveTestSection(['schedule' => self::SCHEDULE], ['schedule' => $expression]);
            self::fail('An expression Magento\'s scheduler cannot run must refuse the save.');
        } catch (LocalizedException $exception) {
            self::assertSame(
                sprintf(
                    'The field "Cleanup schedule" cannot be saved: "%s" is not a cron expression Magento can run.',
                    $expression
                ),
                $exception->getMessage()
            );
        }
        self::assertFalse($this->isStored('schedule'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedExpressions(): array
    {
        return [
            'a step of zero' => ['*/0 * * * *'],
            'four parts' => ['*/5 * * *'],
            'a letter that names nothing' => ['* * * L *'],
            'a minute too large for an integer' => ['99999999999999999999 * * * *'],
            'a step too large for an integer' => ['*/99999999999999999999 * * * *'],
            'a three-digit step' => ['*/100 * * * *'],
        ];
    }

    public function testARefusedExpressionStopsTheSaveOfTheWholeSection(): void
    {
        $fields = [
            'note' => ['label' => 'Note'],
            'schedule' => self::SCHEDULE,
        ];

        try {
            $this->saveTestSection($fields, ['note' => 'kept?', 'schedule' => '*/0 * * * *']);
            self::fail('A step of zero must refuse the save.');
        } catch (LocalizedException) {
            self::assertFalse($this->isStored('note'));
            self::assertFalse($this->isStored('schedule'));
        }
    }

    public function testTheBackendModelChecksASaveThatCarriesNoLabelNamingThePath(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'The field "hcf_test_section/fields/schedule" cannot be saved: "0 0 * * 1-5/0"'
        );

        $this->saveTestSection(
            ['schedule' => ['backend_model' => CronExpression::class]],
            ['schedule' => '0 0 * * 1-5/0']
        );
    }
}
