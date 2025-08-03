<?php

/**
 * @file
 * Primary configuration file for Drupal Rector.
 */

declare(strict_types=1);

use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal8SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

/**
 * Indicates Drupal Rector how it should upgrade our codebase.
 *
 * @see vendor/palantirnet/drupal-rector/rector.php
 */
return static function (RectorConfig $rectorConfig): void {
  // Adjust the set lists to be more granular to your Drupal requirements.
  // @todo find out how to only load the relevant rector rules.
  //   Should we try and load \Drupal::VERSION and check?
  //   new possible option with ComposerTriggeredSet
  //   https://github.com/rectorphp/rector-src/blob/b5a5739b7d7dde621053adff113449860ed5331f/src/Set/ValueObject/ComposerTriggeredSet.php
  $rectorConfig->sets([
    Drupal8SetList::DRUPAL_8,
    Drupal9SetList::DRUPAL_9,
    Drupal10SetList::DRUPAL_10,
    LevelSetList::UP_TO_PHP_84,
    SetList::TYPE_DECLARATION,
    SetList::CODE_QUALITY,
    SetList::CODING_STYLE,
    SetList::DEAD_CODE,
    SetList::STRICT_BOOLEANS,
    SetList::RECTOR_PRESET,
    SetList::PRIVATIZATION,
    SetList::EARLY_RETURN,
    SetList::INSTANCEOF,
  ]);

  $drupalFinder = new DrupalFinderComposerRuntime();
  $drupalRoot = $drupalFinder->getDrupalRoot();

  $rectorConfig->autoloadPaths([
    $drupalRoot . '/core',
    $drupalRoot . '/modules',
    $drupalRoot . '/profiles',
    $drupalRoot . '/themes',
  ]);

  $rectorConfig->skip([
    '*/upgrade_status/tests/modules/*',
    AddOverrideAttributeToOverriddenMethodsRector::class,
  ]);
  $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
  $rectorConfig->importNames(TRUE, FALSE);
  $rectorConfig->importShortClasses(FALSE);

  $rectorConfig->paths(['web/modules/custom', 'web/themes/custom']);
};
