<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

/**
 * Standalone bootstrap for this module's unit tests.
 *
 * The classes under test are exercised against Composer's autoloader, without booting Magento.
 */

$autoloadCandidates = [
    __DIR__ . '/../../../../autoload.php', // vendor/<v>/<m>/Test/Unit -> vendor/autoload.php
    __DIR__ . '/../../vendor/autoload.php', // module run standalone
    __DIR__ . '/../../../../../../vendor/autoload.php', // app/code/<V>/<M>/Test/Unit -> root vendor
];

$loader = null;
$vendorDir = null;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        $loader = require $candidate;
        $vendorDir = dirname($candidate);
        break;
    }
}

if (!$loader instanceof \Composer\Autoload\ClassLoader || $vendorDir === null) {
    throw new \RuntimeException('Unable to locate Composer autoloader for the test bootstrap.');
}

$loader->addPsr4('Hryvinskyi\\ConfigurationFields\\', dirname(__DIR__, 2));
$loader->addPsr4('Hryvinskyi\\ConfigurationFields\\Test\\Unit\\', __DIR__);

// When running inside a Magento installation, also resolve generated factories
// (e.g. Magento\Framework\HTTP\Client\CurlFactory) from generated/code.
$generated = dirname($vendorDir) . '/generated/code';
if (is_dir($generated)) {
    $loader->add('', [$generated]);
}

// Magento's translate function, which the root autoloader does not load: classes that build
// \Magento\Framework\Phrase messages call it.
$translate = $vendorDir . '/magento/framework/Phrase/__.php';
if (!function_exists('__') && is_file($translate)) {
    require_once $translate;
}
