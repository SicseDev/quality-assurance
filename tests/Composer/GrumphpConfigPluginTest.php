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
use PHPUnit\Framework\Attributes\DataProvider;
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
   * The composer instance.
   */
  private Composer&Stub $composer;

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
    $this->composer = $this->createStub(Composer::class);
    $this->io = $this->createMock(IOInterface::class);

    $this->composer->method('getPackage')->willReturn($this->rootPackage);
    $this->composer->method('getConfig')->willReturn($config);
    $config->method('get')->with('vendor-dir')->willReturn(vfsStream::url('root/vendor'));

    // Create plugin instance.
    $this->grumphpConfigPlugin = new GrumphpConfigPlugin();
  }

  /**
   * Ensures that the grumphp.yml file referenced in GrumphpConfigPlugin exists.
   */
  public function testGrumphpConfigExists(): void {
    // Determine the root directory of this package, assuming this test file
    // is located in 'tests/Composer'.
    $package_root = dirname(__DIR__, 2);

    // Construct the location of the grumphp.yml file that should be provided by
    // this package. As this package is not installed in
    // 'vendor/sicse/quality-assurance', that part should be removed from the
    // GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH constant.
    $vendor_prefix = 'vendor/sicse/quality-assurance/';
    $config_path = GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH;

    // If the path starts with the vendor prefix, remove it.
    $relative_path = $config_path;
    if (str_starts_with($config_path, $vendor_prefix)) {
      $relative_path = substr($config_path, strlen($vendor_prefix));
    }

    $grumphp_config_path = sprintf('%s/%s', $package_root, $relative_path);

    // Verify that the file exists and is not empty.
    $this->assertFileExists($grumphp_config_path, 'grumphp.yml configuration file, meant for projects using this plugin, cannot be found.');
    $this->assertFileIsReadable($grumphp_config_path, 'grumphp.yml configuration file, meant for projects using this plugin, should be readable.');
    $this->assertGreaterThan(0, filesize($grumphp_config_path), 'grumphp.yml configuration file, meant for projects using this plugin, should not be empty.');
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

    // Activate the plugin and execute the function that we are testing.
    $this->grumphpConfigPlugin->activate($this->composer, $this->io);

    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify no config added.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertEmpty($config_path, 'GrumPHP config should not be added when grumphp.yml exists.');
  }

  /**
   * Tests the addGrumphpConfig method with existing config in composer.json.
   */
  public function testAddGrumphpConfigWithExistingComposerConfig(): void {
    // Set up composer.json with an existing config-default-path.
    $extra = [
      'grumphp' => [
        'config-default-path' => 'some/other/path.yml',
      ],
    ];

    // Create composer.json with the specified extra configuration.
    vfsStream::create([
      'composer.json' => json_encode([
        'name' => 'test/project',
        'extra' => $extra,
      ], JSON_THROW_ON_ERROR),
    ], $this->vfsStreamDirectory);

    // Mock the root package to return the existing extra configuration.
    $this->rootPackage->method('getExtra')
      ->willReturn($extra);

    // Activate the plugin and execute the function that we are testing.
    $this->grumphpConfigPlugin->activate($this->composer, $this->io);
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    // Verify config not changed.
    $config_path = $this->getGrumphpConfigPath();
    $this->assertSame('some/other/path.yml', $config_path, 'GrumPHP config should not be modified when it already exists in composer.json.');
  }

  /**
   * Tests the addGrumphpConfig method with different user responses.
   *
   * @param bool $user_consents
   *   Whether the user consents to adding the config.
   * @param string|null $expected_path
   *   The expected config path in composer.json after the operation, or NULL
   *   if none.
   */
  #[DataProvider('addGrumphpConfigUserResponseProvider')]
  public function testAddGrumphpConfigUserResponse(bool $user_consents, ?string $expected_path): void {
    // The default behavior of the provided test doubles is that there is no
    // grumphp.yml file or config-default-path set. In that case we expect that
    // the plugin asks the user for consent.
    $this->io->expects($this->once())
      ->method('askConfirmation')
      ->with(GrumphpConfigPlugin::GRUMPHP_CONFIRMATION_QUESTION)
      ->willReturn($user_consents);

    // Activate the plugin and execute the function that we are testing.
    $this->grumphpConfigPlugin->activate($this->composer, $this->io);
    $this->grumphpConfigPlugin->addGrumphpConfig($this->createStub(Event::class));

    $config_path = $this->getGrumphpConfigPath();
    $this->assertSame($expected_path, $config_path);
  }

  /**
   * Data provider for testAddGrumphpConfigUserResponse.
   *
   * @return \Iterator<string, array{0: bool, 1: string|null}>
   *   Yields test cases with user consent and expected config path.
   */
  public static function addGrumphpConfigUserResponseProvider(): \Iterator {
    yield 'user declines' => [FALSE, NULL];
    yield 'user consents' => [TRUE, GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH];
  }

  /**
   * Tests the uninstall method with different configurations.
   *
   * @param array<string, mixed> $initial_composer_json
   *   The initial composer.json configuration.
   * @param array<string, mixed> $expected_composer_json
   *   The expected composer.json configuration after uninstall.
   */
  #[DataProvider('uninstallConfigurationProvider')]
  public function testUninstall(array $initial_composer_json, array $expected_composer_json): void {
    // Setup composer.json with the provided initial configuration.
    vfsStream::create([
      'composer.json' => json_encode($initial_composer_json, JSON_THROW_ON_ERROR),
    ], $this->vfsStreamDirectory);

    // Mock the root package to return the initial extra configuration.
    $this->rootPackage->method('getExtra')
      ->willReturn($initial_composer_json['extra'] ?? []);

    // Trigger the uninstall function.
    $this->grumphpConfigPlugin->uninstall($this->composer, $this->io);

    // Verify the composer.json reflects the expected final configuration.
    $result_composer_json = $this->getComposerJson();
    $this->assertSame($expected_composer_json, $result_composer_json, 'composer.json should match the expected configuration after uninstall.');
  }

  /**
   * Data provider for testUninstall.
   */
  public static function uninstallConfigurationProvider(): \Iterator {
    yield 'empty configuration' => [
      [],
      [],
    ];

    yield 'only project information' => [
      [
        'name' => 'test/project',
      ],
      [
        'name' => 'test/project',
      ],
    ];

    yield 'empty extra configuration' => [
      [
        'name' => 'test/project',
        'extra' => [],
      ],
      [
        'name' => 'test/project',
        'extra' => [],
      ],
    ];

    yield 'empty GrumPHP configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [],
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [],
        ],
      ],
    ];

    yield 'other extra configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'some-config' => 'value',
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'some-config' => 'value',
        ],
      ],
    ];

    yield 'other GrumPHP configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'some-grumphp-config' => 'value',
          ],
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'some-grumphp-config' => 'value',
          ],
        ],
      ],
    ];

    yield 'different GrumPHP config default path configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'config-default-path' => 'some/other/path.yml',
          ],
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'config-default-path' => 'some/other/path.yml',
          ],
        ],
      ],
    ];

    yield 'our config default path configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'config-default-path' => GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH,
          ],
        ],
      ],
      [
        'name' => 'test/project',
      ],
    ];

    yield 'our config default path with additional GrumPHP configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'some-grumphp-config' => 'value',
            'config-default-path' => GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH,
            'another-grumphp-config' => 'another-value',
          ],
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'grumphp' => [
            'some-grumphp-config' => 'value',
            'another-grumphp-config' => 'another-value',
          ],
        ],
      ],
    ];

    yield 'our config default path and other extra configuration' => [
      [
        'name' => 'test/project',
        'extra' => [
          'some-config' => 'value',
          'grumphp' => [
            'config-default-path' => GrumphpConfigPlugin::GRUMPHP_CONFIG_PATH,
          ],
          'another-config' => 'another-value',
        ],
      ],
      [
        'name' => 'test/project',
        'extra' => [
          'some-config' => 'value',
          'another-config' => 'another-value',
        ],
      ],
    ];
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
