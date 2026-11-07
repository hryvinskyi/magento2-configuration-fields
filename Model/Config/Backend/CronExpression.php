<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Config\Backend;

use Hryvinskyi\ConfigurationFields\Model\Cron\ExpressionRule;
use Hryvinskyi\ConfigurationFields\Model\Field\SavedFieldName;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model for a cron expression field: refuses to store an expression Magento's scheduler cannot run
 *
 * The cron editor checks the expression in the browser; this model checks it again on every save, the command line
 * included, with the same rule (ExpressionRule). An empty value is stored: the field then holds no schedule. The
 * value is stored as posted. A refused value stops the save of its whole section, with a message naming the field
 * and the value.
 *
 * Usage in system.xml:
 * <backend_model>Hryvinskyi\ConfigurationFields\Model\Config\Backend\CronExpression</backend_model>
 */
class CronExpression extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ExpressionRule $expressionRule
     * @param SavedFieldName $fieldName
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ExpressionRule $expressionRule,
        private readonly SavedFieldName $fieldName,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse an expression Magento's scheduler cannot run
     *
     * @return $this
     * @throws LocalizedException When the posted value is not an empty value or a cron expression Magento runs
     */
    public function beforeSave(): self
    {
        $expression = (string) $this->getValue();

        if (!$this->expressionRule->isValidExpression($expression)) {
            throw new LocalizedException(__(
                'The field "%1" cannot be saved: "%2" is not a cron expression Magento can run.',
                $this->fieldName->of($this),
                $expression
            ));
        }

        return parent::beforeSave();
    }
}
