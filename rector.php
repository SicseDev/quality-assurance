<?php

/**
 * @file
 * Configuration file for Rector in this project.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
  ->withRootFiles()
  ->withPhpSets()
  ->withPreparedSets(
    deadCode: TRUE,
    codeQuality: TRUE,
    codingStyle: TRUE,
    typeDeclarations: TRUE,
    privatization: TRUE,
    naming: TRUE,
    instanceOf: TRUE,
    earlyReturn: TRUE,
    strictBooleans: TRUE,
    rectorPreset: TRUE,
    phpunitCodeQuality: TRUE,
    symfonyCodeQuality: TRUE,
    symfonyConfigs: TRUE,
  )
  ->withAttributesSets()
  ->withComposerBased(
    twig: TRUE,
    phpunit: TRUE,
    symfony: TRUE,
  )
  ->withImportNames();
