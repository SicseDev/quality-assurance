<?php

declare(strict_types=1);

namespace Sicse\QualityAssurance\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

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
  public function uninstall(Composer $composer, IOInterface $io): void {
    $this->composer = $composer;
    $this->io = $io;

    // Only remove the configuration if it's set to our package's grumphp.yml.
    if ($this->hasGrumphpConfigInComposer(self::GRUMPHP_CONFIG_PATH)) {
      $this->removeGrumphpConfigPath();
    }
  }

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
   * @param \Composer\Script\Event $event
   *   The event triggered by Composer.
   */
  public function addGrumphpConfig(Event $event): void {
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
    $this->addGrumphpConfigPath(self::GRUMPHP_CONFIG_PATH);
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
   * Checks if GrumPHP configuration is set in composer.json.
   *
   * @param string|null $path
   *   If provided, checks if the 'config-default-path' matches this path. In
   *   case of NULL, only checks if any 'config-default-path' is set.
   *
   * @return bool
   *   TRUE if the configuration meets the requirements, FALSE otherwise.
   */
  private function hasGrumphpConfigInComposer(?string $path = NULL): bool {
    $extra = $this->composer->getPackage()->getExtra();

    if (!isset($extra['grumphp']) || !is_array($extra['grumphp'])) {
      return FALSE;
    }

    if (!isset($extra['grumphp']['config-default-path'])) {
      return FALSE;
    }

    // If we only need to check that any config exists, we can return true now.
    if ($path === NULL) {
      return TRUE;
    }

    // Check if the path matches our specific package path.
    return $extra['grumphp']['config-default-path'] === self::GRUMPHP_CONFIG_PATH;
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
   * Adds the GrumPHP configuration path to composer.json.
   *
   * @param string $path
   *   The path to the GrumPHP configuration file.
   */
  private function addGrumphpConfigPath(string $path): void {
    $jsonFile = $this->getComposerJsonFile();
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
    $config['extra']['grumphp']['config-default-path'] = $path;
    $jsonFile->write($config);
    $this->io->write(sprintf('<info>GrumPHP configuration path set to %s</info>', $path));
  }

  /**
   * Removes the GrumPHP configuration path from composer.json.
   */
  private function removeGrumphpConfigPath(): void {
    $jsonFile = $this->getComposerJsonFile();
    $config = (array) $jsonFile->read();

    // Ensure 'extra' is an array before accessing 'grumphp'.
    if (!isset($config['extra']) || !is_array($config['extra'])) {
      return;
    }

    // Ensure 'grumphp' is an array before accessing 'config-default-path'.
    if (!isset($config['extra']['grumphp']) || !is_array($config['extra']['grumphp'])) {
      return;
    }

    // If the necessary structure doesn't exist, there's nothing to remove.
    if (!isset($config['extra']['grumphp']['config-default-path'])) {
      return;
    }

    // Remove the config-default-path.
    unset($config['extra']['grumphp']['config-default-path']);

    // Clean up empty structures. We deliberately only clean up when the grumphp
    // and extra sections were set to store the config-default-path.
    if (empty($config['extra']['grumphp'])) {
      unset($config['extra']['grumphp']);
    }

    if (empty($config['extra'])) {
      unset($config['extra']);
    }

    // Write the updated configuration back to composer.json.
    $jsonFile->write($config);
    $this->io->write('<info>GrumPHP configuration has been removed from composer.json</info>');
  }

  /**
   * Gets the JsonFile object for the composer.json file in the project root.
   *
   * @return \Composer\Json\JsonFile
   *   The JsonFile object for composer.json.
   */
  private function getComposerJsonFile(): JsonFile {
    $vendor_dir = $this->composer->getConfig()->get('vendor-dir');
    $root_dir = $vendor_dir . '/..';
    $composer_file = $root_dir . '/composer.json';
    return new JsonFile($composer_file);
  }

}
