<?php

declare(strict_types=1);

namespace Sicse\QualityAssurance\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Json\JsonFile;

/**
 * Defines Composer a plugin to manage GrumPHP configuration in Drupal projects.
 *
 * This plugin updates the composer.json file to include the path to the GrumPHP
 * configuration file provided by the quality assurance package, in case it
 * doesn't exist yet.
 *
 * It is designed to be used in Drupal projects to ensure that GrumPHP is
 * configured correctly for quality assurance tasks.
 */
class GrumphpConfigPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Default relative path to GrumPHP configuration file for Drupal projects.
   */
  public const string GRUMPHP_CONFIG_PATH = 'vendor/sicse/quality-assurance/config/grumphp.yml';

  /**
   * Question presented to the user when no GrumPHP config is found.
   */
  public const string GRUMPHP_CONFIRMATION_QUESTION = 'No GrumPHP configuration found. Would you like to use the configuration provided by the quality assurance package? [Y/n] ';

  /**
   * The composer instance.
   */
  private Composer $composer;

  /**
   * The IO interface instance.
   */
  private IOInterface $io;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io): void {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io): void {}

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io): void {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScriptEvents::POST_INSTALL_CMD => 'addGrumphpConfig',
    ];
  }

  /**
   * Adds the GrumPHP configuration to the composer.json file.
   *
   * This method is called after the composer install command is executed.
   * It checks if the 'config-default-path' exists in composer.json and adds it
   * if it does not exist, pointing to the grumphp.yml file provided in this
   * project.
   *
   * @param \Composer\Installer\PackageEvent $packageEvent
   *   The event triggered by Composer.
   */
  public function addGrumphpConfig(PackageEvent $packageEvent): void {
    // There is nothing to do when the root-project already has a grumphp.yml
    // configuration file or when the 'config-default-path' is already set.
    if ($this->hasGrumphpConfigFile() || $this->hasGrumphpConfigInComposer()) {
      return;
    }

    // In case nothing is provided, ask the user whether they want to use the
    // grumphp.yml configuration file provided in this project or not.
    if (!$this->askUserForConsent()) {
      return;
    }

    // Update the composer.json file with the 'config-default-path' pointing to
    // the grumphp.yml provided in this project.
    $this->updateComposerJson();
  }

  /**
   * Checks if a grumphp.yml file exists in the project root.
   *
   * @return bool
   *   TRUE if the file exists, FALSE otherwise.
   */
  private function hasGrumphpConfigFile(): bool {
    $root_dir = $this->composer->getConfig()->get('vendor-dir') . '/..';

    return file_exists($root_dir . '/grumphp.yml')
      || file_exists($root_dir . '/grumphp.yml.dist')
      || file_exists($root_dir . '/grumphp.dist.yml');
  }

  /**
   * Checks if GrumPHP configuration is already set in composer.json.
   *
   * @return bool
   *   TRUE if the configuration is already set, FALSE otherwise.
   */
  private function hasGrumphpConfigInComposer(): bool {
    $extra = $this->composer->getPackage()->getExtra();

    return is_array($extra['grumphp'] ?? NULL) && isset($extra['grumphp']['config-default-path']);
  }

  /**
   * Asks the user if they want to use the provided grumphp.yml.
   *
   * @return bool
   *   TRUE if the user consents, FALSE otherwise.
   */
  private function askUserForConsent(): bool {
    return (bool) $this->io->askConfirmation(self::GRUMPHP_CONFIRMATION_QUESTION);
  }

  /**
   * Updates the composer.json file with GrumPHP configuration.
   */
  private function updateComposerJson(): void {
    $root_dir = $this->composer->getConfig()->get('vendor-dir') . '/..';
    $composer_file = sprintf('%s/composer.json', $root_dir);
    $jsonFile = new JsonFile($composer_file);
    $config = (array) $jsonFile->read();

    // Ensure 'extra' is an array.
    if (!isset($config['extra']) || !is_array($config['extra'])) {
      $config['extra'] = [];
    }

    // Ensure 'grumphp' is an array.
    if (!isset($config['extra']['grumphp']) || !is_array($config['extra']['grumphp'])) {
      $config['extra']['grumphp'] = [];
    }

    // Set the config-default-path.
    $config['extra']['grumphp']['config-default-path'] = self::GRUMPHP_CONFIG_PATH;

    $jsonFile->write($config);

    $this->io->write(sprintf('<info>GrumPHP configuration path set to %s</info>', self::GRUMPHP_CONFIG_PATH));
  }

}
