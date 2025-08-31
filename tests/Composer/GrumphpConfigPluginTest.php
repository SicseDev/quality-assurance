<?php

declare(strict_types=1);

namespace Sicse\QualityAssurance\Tests\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sicse\QualityAssurance\Composer\GrumphpConfigPlugin;

/**
 * Tests the GrumphpConfigPlugin functionality.
 */
#[CoversClass(GrumphpConfigPlugin::class)]
final class GrumphpConfigPluginTest extends TestCase {

  /**
   * The virtual file system.
   */
  private vfsStreamDirectory $vfsStreamDirectory;

  /**
   * The IO interface instance.
   */
  private IOInterface&MockObject $io;

  /**
   * The root package.
   */
  private RootPackage&Stub $rootPackage;

  /**
   * The plugin instance being tested.
   */
  private GrumphpConfigPlugin $grumphpConfigPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Initialize a virtual file system with a basic composer.json file.
    $this->vfsStreamDirectory = vfsStream::setup('root', NULL, [
      'composer.json' => json_encode(['name' => 'test/project'], JSON_THROW_ON_ERROR),
    ]);

    // Set up all needed test doubles.
    $this->rootPackage = $this->createStub(RootPackage::class);
    $config = $this->createStub(Config::class);
    $composer = $this->createStub(Composer::class);
    $this->io = $this->createMock(IOInterface::class);

    $composer->method('getPackage')->willReturn($this->rootPackage);
    $composer->method('getConfig')->willReturn($config);
    $config->method('get')->with('vendor-dir')->willReturn(vfsStream::url('root/vendor'));

    // Create plugin instance.
    $this->grumphpConfigPlugin = new GrumphpConfigPlugin();
    $this->grumphpConfigPlugin->activate($composer, $this->io);
  }

  /**
   * Tests that the plugin subscribes to the correct events.
   */
  public function testGetSubscribedEvents(): void {
    $events = GrumphpConfigPlugin::getSubscribedEvents();
    $this->assertArrayHasKey('post-install-cmd', $events);
    $this->assertEquals('addGrumphpConfig', $events['post-install-cmd']);
  }

  /**
   * Tests the addGrumphpConfig method with existing grumphp.yml file.
   */
  public function testAddGrumphpConfigWithExistingConfigFile(): void {
    // Create a grumphp.yml file in the project root.
    vfsStream::create([
      'grumphp.yml' => 'existing config',
    ], $this->vfsStreamDirectory);

    // Execute the function that we are testing.
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify no config added.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertEmpty($config_path, 'GrumPHP config should not be added when grumphp.yml exists.');
  }

  /**
   * Tests the addGrumphpConfig method with existing config in composer.json.
   */
  public function testAddGrumphpConfigWithExistingComposerConfig(): void {
    // Setup composer.json with existing GrumPHP config.
    $extra = ['grumphp' => ['config-default-path' => 'some/path']];
    vfsStream::create([
      'composer.json' => json_encode([
        'name' => 'test/project',
        'extra' => $extra,
      ], JSON_THROW_ON_ERROR),
    ], $this->vfsStreamDirectory);

    // Ensure the extra configuration is returned when the getExtra() function
    // is used.
    $this->rootPackage->method('getExtra')
      ->willReturn($extra);

    // Execute the function that we are testing.
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify config not changed.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertSame('some/path', $config_path, 'GrumPHP config should not be modified when it already exists in composer.json.');
  }

  /**
   * Tests the addGrumphpConfig method when user declines to add config.
   */
  public function testAddGrumphpConfigWhenUserDeclines(): void {
    // The default behavior of the provided test doubles is that there is no
    // grumphp.yml file or config-default-path set. In that case we expect that
    // the plugin asks the user for consent. In this test case we mock the IO
    // interface to simulate the user declining to add the config.
    $this->io->expects($this->once())
      ->method('askConfirmation')
      ->with(GrumphpConfigPlugin::GRUMPHP_CONFIRMATION_QUESTION)
      ->willReturn(FALSE);

    // Execute the function that we are testing.
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify no config added.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertEmpty($config_path, 'GrumPHP config should not be added when the user declines.');
  }

  /**
   * Tests the addGrumphpConfig method when user agrees to add config.
   */
  public function testAddGrumphpConfigWithUserConsent(): void {
    // The default behavior of the provided test doubles is that there is no
    // grumphp.yml file or config-default-path set. In that case we expect that
    // the plugin asks the user for consent. In this test case we mock the IO
    // interface to simulate the user agreeing to add the config.
    $this->io->expects($this->once())
      ->method('askConfirmation')
      ->with(GrumphpConfigPlugin::GRUMPHP_CONFIRMATION_QUESTION)
      ->willReturn(TRUE);

    // Execute the function that we are testing.
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify config added.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertSame(
      GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH,
      $config_path,
      'GrumPHP config should be added when the user consents.',
    );
  }

  /**
   * Helper to safely read the GrumPHP config-default-path from composer.json.
   *
   * @return string|null
   *   The config-default-path if set, or NULL if not set or invalid.
   *
   * @throws \JsonException
   *   If the composer.json cannot be parsed.
   */
  private function getGrumphpConfigPath(): ?string {
    $composer_json = $this->getComposerJson();

    $extra = $composer_json['extra'] ?? NULL;
    if (!is_array($extra)) {
      return NULL;
    }

    $grumphp = $extra['grumphp'] ?? NULL;
    if (!is_array($grumphp)) {
      return NULL;
    }

    $path = $grumphp['config-default-path'] ?? NULL;
    return is_string($path) ? $path : NULL;
  }

  /**
   * Helper method to get the current composer.json content.
   *
   * @return mixed[]
   *   The decoded composer.json content as an associative array.
   *
   * @throws \RuntimeException|\JsonException
   *   If the file cannot be read or parsed.
   */
  private function getComposerJson(): array {
    $composer_json_path = vfsStream::url('root/composer.json');
    $contents = file_get_contents($composer_json_path);

    if ($contents === FALSE) {
      throw new \RuntimeException('Unable to read composer.json file.');
    }

    return (array) json_decode($contents, TRUE, 512, JSON_THROW_ON_ERROR);
  }

}
