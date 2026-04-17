<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// SymfonySerializer is opt-in, listed in suggest; anyone using it will have symfony/serializer installed anyway
$config->ignoreErrorsOnPackage('symfony/serializer', [ErrorType::DEV_DEPENDENCY_IN_PROD]);

// use of ext-pcntl is guarded behind extension_loaded('pcntl')
$config->ignoreErrorsOnExtension('ext-pcntl', [ErrorType::DEV_DEPENDENCY_IN_PROD]);


return $config;