<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A tag list's values checked on save in the admin, through Magento's config model, against the pattern its field
 * shows in the browser
 *
 * The container is configured as an admin request configures it: the admin area's declarations, the field blocks'
 * virtual types among them, applied before the save. The settings still come from the declared `tagListConfig`, read
 * from the areas' declarations once per process, so a block class that replaces its settings in code is not seen,
 * exactly as in every other area. A preference declared for the admin area that an admin request has not applied
 * shows whether the save applied anything to the container.
 */
#[AppArea('adminhtml')]
#[DbIsolation(false)]
class TagListValidationTest extends TagListSectionTestCase
{
    /**
     * @var AdminAreaDeclarations
     */
    private AdminAreaDeclarations $declarations;

    protected function setUp(): void
    {
        parent::setUp();
        Bootstrap::getObjectManager()->configure($this->fieldBlockTypes() + $this->backendTypes());
        $this->declarations = $this->declareForTheAdminAreaOnly($this->adminDeclarations());
    }

    /**
     * @param string $field
     * @param string $posted
     * @param string $stored
     * @return void
     */
    #[DataProvider('acceptedValues')]
    public function testAValueTheFieldsOwnPatternAcceptsIsStored(string $field, string $posted, string $stored): void
    {
        $this->save($field, $posted);

        self::assertSame($stored, $this->storedValue($field));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function acceptedValues(): array
    {
        return [
            'routes with slashes and stars' => ['routes', 'mui/*,catalog/product/save', 'mui/*,catalog/product/save'],
            'field names, upper-cased for the match only' => [
                'field_names', 'password,api_key,x.token-id', 'password,api_key,x.token-id',
            ],
            'IPv4 and IPv6 addresses' => ['ips', "10.0.0.1\n2001:db8::1", "10.0.0.1\n2001:db8::1"],
            'user agents with spaces and punctuation' => [
                'user_agents',
                "Mozilla/5.0 (compatible; Googlebot/2.1)\nbingbot",
                "Mozilla/5.0 (compatible; Googlebot/2.1)\nbingbot",
            ],
            'paths' => ['paths', "customer/section/load\ncheckout/cart/*", "customer/section/load\ncheckout/cart/*"],
            'parameter names' => ['parameters', "price\nfilter[color]", "price\nfilter[color]"],
            'postcodes with a space' => ['postcodes', "SW1A 1AA\nEC1A 1BB", "SW1A 1AA\nEC1A 1BB"],
            'lower-case postcodes on an upper-case field, stored as posted' => [
                'postcodes', "sw1a 1aa\nec1a 1bb", "sw1a 1aa\nec1a 1bb",
            ],
            'the default pattern' => ['codes', "ABC.def\nx#y/z`", "ABC.def\nx#y/z`"],
            'the frontend model\'s pattern, not the backend model\'s own' => ['letters', "abc\nDEF", "abc\nDEF"],
            'no frontend model: the backend model\'s own pattern' => ['digits', "1\n23", "1\n23"],
            'a frontend model of another kind: the backend model\'s own pattern' => [
                'digits_beside_a_cron_editor', "1\n23", "1\n23",
            ],
            'a block that replaces its settings in code: the declared pattern' => [
                'preset', "10.0.0.1\n2001:db8::1", "10.0.0.1\n2001:db8::1",
            ],
        ];
    }

    /**
     * @param string $field
     * @param string $posted
     * @param string $message
     * @return void
     */
    #[DataProvider('refusedValues')]
    public function testAValueTheFieldsOwnPatternRefusesStopsTheSave(
        string $field,
        string $posted,
        string $message
    ): void {
        try {
            $this->save($field, $posted);
            self::fail('A value the field\'s pattern refuses must refuse the save.');
        } catch (LocalizedException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
        self::assertFalse($this->isStored($field));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function refusedValues(): array
    {
        return [
            'a route with a space' => [
                'routes', 'mui/*,catalog product',
                'The field "Ignored routes" cannot be saved with the value "catalog product": ' . self::NEUTRAL,
            ],
            'a field name with a colon' => [
                'field_names', 'password,card:number',
                'The field "Secret field names" cannot be saved with the value "card:number": ' . self::NEUTRAL,
            ],
            'a host name in the address field' => [
                'ips', "10.0.0.1\nlocalhost",
                'The field "Excluded IPs" cannot be saved with the value "localhost": ' . self::IP_MESSAGE,
            ],
            'a carriage return inside a user agent' => [
                'user_agents', "Googlebot\rEvil",
                "The field \"Excluded user agents\" cannot be saved with the value \"Googlebot\rEvil\": "
                . self::NEUTRAL,
            ],
            'a comma in a path' => [
                'paths', 'customer/section/load,checkout',
                'The field "Excluded paths" cannot be saved with the value "customer/section/load,checkout": '
                . self::PATH_MESSAGE,
            ],
            'the IPv6 address the address field accepts, in the parameter field beside it' => [
                'parameters', '2001:db8::1',
                'The field "Ignored parameters" cannot be saved with the value "2001:db8::1": '
                . self::PARAMETER_MESSAGE,
            ],
            'a postcode with a dash' => [
                'postcodes', 'SW1A-1AA',
                'The field "Postcodes" cannot be saved with the value "SW1A-1AA": ' . self::NEUTRAL,
            ],
            'a space under the default pattern, named as posted' => [
                'codes', "ABC\na b",
                'The field "Codes" cannot be saved with the value "a b": ' . self::DEFAULT_MESSAGE,
            ],
            'a pattern the server cannot compile' => [
                'broken', 'A',
                'The field "Broken" cannot be saved: its validation pattern is not a regular expression the server'
                . ' can check.',
            ],
            'no frontend model: the backend model\'s own pattern' => [
                'digits', "1\nabc",
                'The field "Digits" cannot be saved with the value "abc": ' . self::NEUTRAL,
            ],
            'a frontend model of another kind: the backend model\'s own pattern' => [
                'digits_beside_a_cron_editor', "1\nabc",
                'The field "Digits" cannot be saved with the value "abc": ' . self::NEUTRAL,
            ],
        ];
    }

    public function testAdminSavesReadTheDeclarationsOnceAndApplyNothing(): void
    {
        $this->save('ips', "10.0.0.1\n2001:db8::1");
        $this->save('paths', 'customer/section/load');

        self::assertSame("10.0.0.1\n2001:db8::1", $this->storedValue('ips'));
        self::assertSame('customer/section/load', $this->storedValue('paths'));
        self::assertSame(['global' => 1, 'adminhtml' => 1], $this->declarations->loads());
        $this->assertRunningContainerUntouched();
    }

    /**
     * Save the test section with one of its fields posted
     *
     * @param string $field
     * @param string $posted
     * @return void
     */
    private function save(string $field, string $posted): void
    {
        $this->saveTestSection($this->fields(), [$field => $posted]);
    }
}
