<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\ViewModel;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Hryvinskyi\ConfigurationFields\ViewModel\CronEditorField;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

/**
 * The cron field's data: the stored expression as stored, the classes of its hidden input, the five parts, the syntax
 * rows, the wording, the disabled state and the mount of its script
 */
class CronEditorFieldTest extends FormElementTestCase
{
    public function testCarriesTheStoredExpressionPartsSyntaxAndWording(): void
    {
        $element = $this->element(
            ['html_id' => 'cron', 'name' => 'groups[g][fields][cron][value]', 'value' => '*/5 1 * * 0']
        );
        $rootId = (new ElementIdentity())->rootId($element);
        $config = $this->viewModel()->config($element);

        self::assertSame('cron', $config['inputId']);
        self::assertSame('groups[g][fields][cron][value]', $config['inputName']);
        self::assertSame('cron_inherit', $config['inheritId']);
        self::assertSame('*/5 1 * * 0', $config['value']);
        self::assertSame(
            [
                ['part' => 'minute', 'inputId' => $rootId . '-minute', 'label' => 'minute', 'allowed' => '0-59'],
                ['part' => 'hour', 'inputId' => $rootId . '-hour', 'label' => 'hour', 'allowed' => '0-23'],
                [
                    'part' => 'dayOfMonth',
                    'inputId' => $rootId . '-dayOfMonth',
                    'label' => 'day (month)',
                    'allowed' => '1-31',
                ],
                [
                    'part' => 'month',
                    'inputId' => $rootId . '-month',
                    'label' => 'month',
                    'allowed' => '1-12, jan-dec',
                ],
                [
                    'part' => 'dayOfWeek',
                    'inputId' => $rootId . '-dayOfWeek',
                    'label' => 'day (week)',
                    'allowed' => '0-6, sun-sat',
                ],
            ],
            $config['parts']
        );
        self::assertSame(
            [
                ['symbol' => '*', 'meaning' => 'any value'],
                ['symbol' => ',', 'meaning' => 'value list separator'],
                ['symbol' => '-', 'meaning' => 'range of values'],
                ['symbol' => '/', 'meaning' => 'step values'],
            ],
            $config['syntax']
        );
        self::assertSame('allowed values', $config['allowedValuesLabel']);
        self::assertSame(
            [
                '#' . $rootId => [
                    CronEditorField::COMPONENT => [
                        'inputId' => 'cron',
                        'inheritId' => 'cron_inherit',
                        'disabled' => false,
                        'value' => '*/5 1 * * 0',
                        'locale' => 'en_US',
                        'wording' => [
                            'invalidExpression' => 'Invalid cron expression.',
                            'invalidFields' => 'Invalid cron expression. Please check highlighted fields.',
                            'undescribable' => 'This schedule cannot be described in words.',
                            'allowedValues' => 'allowed values',
                            'anyValue' => 'any value',
                            'valueListSeparator' => 'value list separator',
                            'rangeOfValues' => 'range of values',
                            'stepValues' => 'step values',
                            'expressionRequired' => 'This field holds a schedule. Enter a cron expression; an empty'
                                . ' value would stop the job from running.',
                        ],
                    ],
                ],
            ],
            $config['mount']['init']
        );
    }

    public function testTheScriptIsMountedWithTheLocaleOfTheInterfaceTheResolverReports(): void
    {
        $element = $this->element(['html_id' => 'cron']);
        $rootId = (new ElementIdentity())->rootId($element);

        foreach (['de_DE', 'pt_BR', 'zh_Hans_CN'] as $locale) {
            $init = $this->viewModel($locale)->config($element)['mount']['init'];

            self::assertSame($locale, $init['#' . $rootId][CronEditorField::COMPONENT]['locale']);
        }
    }

    public function testAnEmptyFieldStaysEmpty(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'cron']));

        self::assertSame('', $config['value']);
    }

    public function testDisabledIsTheElementsDisabledFlagNotWhetherItCouldInherit(): void
    {
        $couldInherit = ['html_id' => 'cron', 'can_use_default_value' => true, 'can_restore_to_default' => true];

        self::assertFalse($this->viewModel()->config($this->element($couldInherit))['disabled']);
        self::assertTrue($this->viewModel()->config($this->element($couldInherit + ['disabled' => true]))['disabled']);
    }

    public function testTheHiddenInputCarriesTheRuleClassAndTheElementsOwnClasses(): void
    {
        self::assertSame(
            'validate-cron-expression',
            $this->viewModel()->config($this->element(['html_id' => 'cron']))['valueClass']
        );
        self::assertSame(
            'validate-cron-expression required-entry shared',
            $this->viewModel()->config(
                $this->element(['html_id' => 'cron', 'class' => ' required-entry  validate-cron-expression shared'])
            )['valueClass']
        );
    }

    public function testAStoredValueThatIsNotUtf8IsPrintableAndSerializable(): void
    {
        $config = $this->viewModel()->config($this->element(['html_id' => 'cron', 'value' => "a\xb1"]));

        self::assertTrue(mb_check_encoding($config['value'], 'UTF-8'));
        self::assertStringStartsWith('a', $config['value']);
        self::assertJson($config['mount']['initJson']);
    }

    public function testPartIdsAreSafeInASelectorWhateverTheHtmlId(): void
    {
        foreach ($this->viewModel()->config($this->element(['html_id' => 'a"b<c> d']))['parts'] as $part) {
            self::assertMatchesRegularExpression('/^hcf-[A-Za-z0-9_-]+$/', $part['inputId']);
        }
    }

    /**
     * @param string $locale The locale the resolver reports
     * @return CronEditorField
     */
    private function viewModel(string $locale = 'en_US'): CronEditorField
    {
        $identity = new ElementIdentity();
        $resolver = $this->createStub(ResolverInterface::class);
        $resolver->method('getLocale')->willReturn($locale);

        return new CronEditorField(
            $identity,
            new StoredValue(),
            new WidgetMount($identity, new JsonHexTag()),
            $resolver
        );
    }
}
