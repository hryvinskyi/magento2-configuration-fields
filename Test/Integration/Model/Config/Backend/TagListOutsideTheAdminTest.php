<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Magento\Config\Console\Command\ConfigSetCommand;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\State;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A tag list's values checked against its field's own pattern when the save runs outside the admin: in the
 * storefront, in a cron job, with no area at all (a setup patch, a command), and through `config:set`
 *
 * The field blocks' virtual types are declared for the admin area alone, as consumers declare them in their
 * `etc/adminhtml/di.xml`, and the container runs with the declarations of the area under test, so only the admin
 * area's own declarations know the pattern. The backend models' virtual types are declared for every area. A save
 * that fell back to the backend model's own pattern would refuse the IPv6 address and the user agent with a space.
 * After every save, the admin area's test declarations, a preference among them, must still be absent from the
 * running container, whatever route could have applied them.
 */
#[DbIsolation(false)]
class TagListOutsideTheAdminTest extends TagListSectionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Bootstrap::getObjectManager()->configure($this->backendTypes());
        $this->declareForTheAdminAreaOnly($this->adminDeclarations());
        $this->useTestSection($this->fields());
    }

    /**
     * @param string $field
     * @param string $posted
     * @param string|null $refusal
     * @return void
     */
    #[AppArea('frontend')]
    #[DataProvider('decisions')]
    public function testTheStorefrontAreaAppliesTheFieldsOwnPattern(
        string $field,
        string $posted,
        ?string $refusal
    ): void {
        self::assertSame(Area::AREA_FRONTEND, $this->areaCode());

        $this->assertDecision($field, $posted, $refusal);
        $this->assertAdminDeclarationsStayOutOfTheContainer();
    }

    /**
     * @param string $field
     * @param string $posted
     * @param string|null $refusal
     * @return void
     */
    #[AppArea('crontab')]
    #[DataProvider('decisions')]
    public function testTheCronAreaAppliesTheFieldsOwnPattern(string $field, string $posted, ?string $refusal): void
    {
        $this->state()->setAreaCode(Area::AREA_CRONTAB);

        $this->assertDecision($field, $posted, $refusal);
        $this->assertAdminDeclarationsStayOutOfTheContainer();
    }

    /**
     * @param string $field
     * @param string $posted
     * @param string|null $refusal
     * @return void
     */
    #[AppArea('global')]
    #[DataProvider('decisions')]
    public function testCodeInNoAreaAppliesTheFieldsOwnPattern(string $field, string $posted, ?string $refusal): void
    {
        self::assertNull($this->areaCode());

        $this->assertDecision($field, $posted, $refusal);
        $this->assertAdminDeclarationsStayOutOfTheContainer();
    }

    /**
     * Code that sets the admin area without an admin request: the container has none of the admin area's
     * declarations, and the save still checks against the declared settings, not what a block builds in code
     *
     * @return void
     */
    #[AppArea('global')]
    public function testAnAdminAreaSetOutsideAnAdminRequestAppliesTheDeclaredPattern(): void
    {
        $scope = Bootstrap::getObjectManager()->get(ScopeInterface::class);
        self::assertInstanceOf(ScopeInterface::class, $scope);
        $previousArea = $this->areaCode();
        $previousScope = $scope->getCurrentScope();
        $this->state()->setAreaCode(Area::AREA_ADMINHTML);

        try {
            $this->assertDecision(
                'preset',
                'localhost',
                'The field "hcf_test_section/fields/preset" cannot be saved with the value "localhost": '
                . self::IP_MESSAGE
            );
            $this->assertDecision('preset', "10.0.0.1\n2001:db8::1", null);
            $this->assertAdminDeclarationsStayOutOfTheContainer();
        } finally {
            (new \ReflectionProperty(State::class, '_areaCode'))->setValue($this->state(), $previousArea);
            $scope->setCurrentScope($previousScope);
        }
    }

    /**
     * Values the field's own pattern decides differently from the backend model's default one
     *
     * @return array<string, array{string, string, string|null}>
     */
    public static function decisions(): array
    {
        return [
            'an IPv6 address' => ['ips', "10.0.0.1\n2001:db8::1", null],
            'a user agent with a space' => ['user_agents', 'Mozilla/5.0 (compatible; Googlebot/2.1)', null],
            'a host name in the address field, refused with the field\'s message' => [
                'ips', "10.0.0.1\nlocalhost",
                'The field "hcf_test_section/fields/ips" cannot be saved with the value "localhost": '
                . self::IP_MESSAGE,
            ],
            'a no-break space in a path, refused with the field\'s message' => [
                'paths', "customer/section\u{00A0}load",
                "The field \"hcf_test_section/fields/paths\" cannot be saved with the value \"customer/section\u{00A0}"
                . 'load": ' . self::PATH_MESSAGE,
            ],
        ];
    }

    #[AppArea('global')]
    public function testConfigSetWithNoAreaSetBeforehandAppliesTheFieldsOwnPattern(): void
    {
        self::assertNull($this->areaCode());
        $command = Bootstrap::getObjectManager()->create(ConfigSetCommand::class);
        self::assertInstanceOf(ConfigSetCommand::class, $command);
        $tester = new CommandTester($command);

        $refused = $tester->execute(['path' => self::path('ips'), 'value' => "10.0.0.1\nlocalhost"]);

        self::assertSame(1, $refused);
        self::assertStringContainsString(
            'The field "Excluded IPs" cannot be saved with the value "localhost": ' . self::IP_MESSAGE,
            $tester->getDisplay()
        );
        self::assertFalse($this->isStored('ips'));

        $accepted = $tester->execute(['path' => self::path('ips'), 'value' => "10.0.0.1\n2001:db8::1"]);

        self::assertSame(0, $accepted, $tester->getDisplay());
        self::assertSame("10.0.0.1\n2001:db8::1", $this->storedValue('ips'));
    }

    /**
     * Save one field through its backend model, as code outside the configuration form saves a value, and check that
     * it is stored, or refused with the message
     *
     * @param string $field
     * @param string $posted
     * @param string|null $refusal The expected message, or null when the value is to be stored
     * @return void
     */
    private function assertDecision(string $field, string $posted, ?string $refusal): void
    {
        $value = Bootstrap::getObjectManager()->create($this->backendOf($field));
        self::assertInstanceOf(Value::class, $value);
        $value->setData(['path' => self::path($field), 'scope' => 'default', 'scope_id' => 0, 'value' => $posted]);

        try {
            $value->save();
        } catch (LocalizedException $exception) {
            self::assertSame($refusal, $exception->getMessage());
            self::assertFalse($this->isStored($field));

            return;
        }
        self::assertNull($refusal, 'The value must have been refused.');
        self::assertSame($posted, $this->storedValue($field));
    }

    /**
     * Check that no route applied the admin area's test declarations to the running container: its preference does
     * not take effect, and the field blocks' virtual types stay unknown to it
     *
     * @return void
     */
    private function assertAdminDeclarationsStayOutOfTheContainer(): void
    {
        $this->assertRunningContainerUntouched();
        $config = Bootstrap::getObjectManager()->get(ObjectManagerConfig::class);
        self::assertInstanceOf(ObjectManagerConfig::class, $config);
        self::assertSame('HcfTestIpListField', $config->getInstanceType('HcfTestIpListField'));
    }

    /**
     * The area the code runs in, or null when none is set
     *
     * @return string|null
     */
    private function areaCode(): ?string
    {
        try {
            return $this->state()->getAreaCode();
        } catch (LocalizedException) {
            return null;
        }
    }

    /**
     * @return State
     */
    private function state(): State
    {
        $state = Bootstrap::getObjectManager()->get(State::class);
        self::assertInstanceOf(State::class, $state);

        return $state;
    }
}
