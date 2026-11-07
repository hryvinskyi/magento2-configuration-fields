<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CronEditor;
use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\TagList as TagListField;
use Hryvinskyi\ConfigurationFields\Model\Config\Backend\TagList;
use Hryvinskyi\ConfigurationFields\Model\TagList\CodecPool;
use Hryvinskyi\ConfigurationFields\Test\Integration\Model\Config\Backend\Fixture\PresetTagListField;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * A section of tag-list fields wired the way consumers wire them: the pattern, the case rule and the message on a
 * virtual type of the field block (the frontend model), the separator and the save type on a virtual type of the
 * backend model
 *
 * The patterns are the ones the tag lists of this code base use, copied as values: routes with slashes, field names,
 * IPv4 and IPv6 addresses, user agents, request paths, parameter names and upper-case postcodes. The address, user
 * agent, path and parameter fields share one backend virtual type, as a consumer may wire them, and each is still
 * checked by its own pattern.
 */
abstract class TagListSectionTestCase extends SectionSaveTestCase
{
    protected const NEUTRAL = 'This value does not match the format this field accepts.';
    protected const DEFAULT_MESSAGE = 'Use letters, digits and . ! # $ % & \' * + / = ? ^ _ ` " { | } ~ - only,'
        . ' without spaces.';
    protected const IP_MESSAGE = 'Enter a valid IPv4 or IPv6 address.';
    protected const PATH_MESSAGE = 'Path patterns cannot contain spaces or commas.';
    protected const PARAMETER_MESSAGE = 'Only letters, digits and _ . [ ] - are allowed in a parameter name.';

    /**
     * A class the admin area's test declarations prefer for the codec pool, which exists nowhere: a container that
     * had those declarations applied could not build the pool
     */
    private const NEVER_APPLIED = 'HcfTestNeverAppliedCodecPool';

    /**
     * Frontend model virtual type => its `tagListConfig`
     */
    private const FIELD_BLOCKS = [
        'HcfTestRouteListField' => ['validation' => '^[A-Za-z0-9_*/]+$', 'uppercase' => false, 'separator' => ','],
        'HcfTestFieldNameListField' => ['validation' => '^[A-Za-z0-9_.\-]+$', 'separator' => ','],
        'HcfTestIpListField' => [
            'validation' => '^(\d{1,3}(\.\d{1,3}){3}|[0-9A-Fa-f:]*:[0-9A-Fa-f:]*)$',
            'validation_message' => self::IP_MESSAGE,
            'uppercase' => false,
        ],
        'HcfTestUserAgentListField' => ['validation' => '^.+$', 'uppercase' => false],
        'HcfTestPathListField' => [
            'validation' => '^[^\s,]+$',
            'validation_message' => self::PATH_MESSAGE,
            'uppercase' => false,
        ],
        'HcfTestParameterListField' => [
            'validation' => '^[A-Za-z0-9_.\[\]-]+$',
            'validation_message' => self::PARAMETER_MESSAGE,
            'uppercase' => false,
        ],
        'HcfTestPostcodeListField' => ['validation' => '^[A-Z0-9 ]+$', 'uppercase' => true],
        'HcfTestBrokenPatternField' => ['validation' => '^[A-Z+$'],
        'HcfTestLetterListField' => ['validation' => '^[A-Z]+$'],
    ];

    /**
     * Backend model virtual type => its `tagListConfig`
     */
    private const BACKENDS = [
        'HcfTestCommaBackend' => ['separator' => ','],
        'HcfTestLineBackend' => ['separator' => '\n', 'save_type' => 'separator'],
        'HcfTestDigitBackend' => ['validation' => '^[0-9]+$', 'uppercase' => false],
    ];

    /**
     * Field id => label, frontend model, backend model
     */
    private const FIELDS = [
        'routes' => ['Ignored routes', 'HcfTestRouteListField', 'HcfTestCommaBackend'],
        'field_names' => ['Secret field names', 'HcfTestFieldNameListField', 'HcfTestCommaBackend'],
        'ips' => ['Excluded IPs', 'HcfTestIpListField', 'HcfTestLineBackend'],
        'user_agents' => ['Excluded user agents', 'HcfTestUserAgentListField', 'HcfTestLineBackend'],
        'paths' => ['Excluded paths', 'HcfTestPathListField', 'HcfTestLineBackend'],
        'parameters' => ['Ignored parameters', 'HcfTestParameterListField', 'HcfTestLineBackend'],
        'postcodes' => ['Postcodes', 'HcfTestPostcodeListField', 'HcfTestLineBackend'],
        'codes' => ['Codes', TagListField::class, TagList::class],
        'broken' => ['Broken', 'HcfTestBrokenPatternField', TagList::class],
        'letters' => ['Letters', 'HcfTestLetterListField', 'HcfTestDigitBackend'],
        'digits' => ['Digits', '', 'HcfTestDigitBackend'],
        'digits_beside_a_cron_editor' => ['Digits', CronEditor::class, 'HcfTestDigitBackend'],
        'preset' => ['Preset addresses', PresetTagListField::class, 'HcfTestLineBackend'],
    ];

    /**
     * The field blocks' virtual types, and the address pattern declared on a block class that replaces it in code, as
     * a consumer declares them for the admin area
     *
     * @return array<string, array{type?: string, arguments: array{tagListConfig: array<string, string|bool>}}>
     */
    protected function fieldBlockTypes(): array
    {
        return $this->virtualTypes(TagListField::class, self::FIELD_BLOCKS) + [
            PresetTagListField::class => ['arguments' => ['tagListConfig' => self::FIELD_BLOCKS['HcfTestIpListField']]],
        ];
    }

    /**
     * Everything the test declares for the admin area alone: the field blocks, and a preference that must never reach
     * the running container
     *
     * @return array<string, array<string, mixed>>
     */
    protected function adminDeclarations(): array
    {
        return $this->fieldBlockTypes() + ['preferences' => [TagListCodecPoolInterface::class => self::NEVER_APPLIED]];
    }

    /**
     * Check, by what the running container builds, that the admin area's test declarations were never applied to it
     *
     * @return void
     */
    protected function assertRunningContainerUntouched(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $config = $objectManager->get(ObjectManagerConfig::class);
        self::assertInstanceOf(ObjectManagerConfig::class, $config);
        self::assertSame(CodecPool::class, $config->getPreference(TagListCodecPoolInterface::class));
        self::assertInstanceOf(CodecPool::class, $objectManager->create(TagListCodecPoolInterface::class));
    }

    /**
     * The backend models' virtual types, as a consumer declares them for every area
     *
     * @return array<string, array{type: string, arguments: array{tagListConfig: array<string, string|bool>}}>
     */
    protected function backendTypes(): array
    {
        return $this->virtualTypes(TagList::class, self::BACKENDS);
    }

    /**
     * The backend model a field is saved through
     *
     * @param string $fieldId
     * @return string
     */
    protected function backendOf(string $fieldId): string
    {
        return self::FIELDS[$fieldId][2];
    }

    /**
     * The test section's fields
     *
     * @return array<string, array<string, string>>
     */
    protected function fields(): array
    {
        $fields = [];
        foreach (self::FIELDS as $id => [$label, $frontendModel, $backendModel]) {
            $fields[$id] = array_filter(
                ['label' => $label, 'frontend_model' => $frontendModel, 'backend_model' => $backendModel],
                static fn (string $value): bool => $value !== ''
            );
        }

        return $fields;
    }

    /**
     * Virtual types of one class, each with its own `tagListConfig`
     *
     * @param string $type
     * @param array<string, array<string, string|bool>> $tagListConfigs Virtual type => its `tagListConfig`
     * @return array<string, array{type: string, arguments: array{tagListConfig: array<string, string|bool>}}>
     */
    private function virtualTypes(string $type, array $tagListConfigs): array
    {
        return array_map(
            static fn (array $tagListConfig): array => [
                'type' => $type,
                'arguments' => ['tagListConfig' => $tagListConfig],
            ],
            $tagListConfigs
        );
    }
}
