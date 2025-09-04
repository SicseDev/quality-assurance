# Quality Assurance

[![CI](https://github.com/SicseDev/quality-assurance/actions/workflows/ci.yml/badge.svg)](https://github.com/SicseDev/quality-assurance/actions/workflows/ci.yml)
[![codecov](https://codecov.io/github/SicseDev/quality-assurance/graph/badge.svg?token=B1OW9O6HLX)](https://codecov.io/github/SicseDev/quality-assurance)
[![Latest Stable Version](http://poser.pugx.org/sicse/quality-assurance/v)](https://packagist.org/packages/sicse/quality-assurance)
[![Total Downloads](http://poser.pugx.org/sicse/quality-assurance/downloads)](https://packagist.org/packages/sicse/quality-assurance)
[![License](http://poser.pugx.org/sicse/quality-assurance/license)](https://packagist.org/packages/sicse/quality-assurance)

A Composer plugin and configuration package for Drupal projects, designed to
enforce code quality, coding standards, and best practices automatically. Ideal
for agencies, maintainers, and teams seeking consistent code review automation
and integration with industry-standard PHP tools.

## Requirements

- PHP 8.3 or higher
- Composer 2.6+
- Compatible with Drupal 10 and 11

## Installation

Require the package as a development dependency:

```bash
composer require sicse/quality-assurance --dev
```

When prompted by Composer to allow plugins, approve the following recommended
plugins to ensure full functionality:

- phpro/grumphp-shim
- phpstan/extension-installer
- ergebnis/composer-normalize
- dealerdirect/phpcodesniffer-composer-installer
- sicse/quality-assurance

When you install this plugin, the GrumPHP package is also installed
automatically. During installation, you will be prompted whether you want to add
a `grumphp.yml` configuration file to your project. When you decline, this
plugin will ask if you want to use the default configuration provided by
this package (i.e., `config/grumphp.yml`).

If you set the GrumPHP `config-default-path` to the default configuration
provided by this package (located in the `config` folder), that configuration
will take precedence over your custom `grumphp.yml` file. To use your own
configuration, do not set `config-default-path` to the package's default config.

## Features

- Out-of-the-box GrumPHP integration: code quality checks run automatically on
  Git commits
- Integration with industry-standard tools:
  - PHP_CodeSniffer: checks code style and enforces Drupal and DrupalPractices
    standards
  - PHPStan: performs static analysis to find bugs and improve code quality
  - Twig CS Fixer: enforces coding standards for Twig template files
  - Rector: automates code refactoring and upgrades for PHP and Drupal
  - And more

## Usage

After installation and enabling the integration, GrumPHP is automatically
configured. Code quality checks will run on every Git commit. You can also run
them manually:

```bash
vendor/bin/grumphp run
```

Note: if you have added your own `grumphp.yml`, make sure not to set the
`config-default-path` to the GrumPHP configuration file provided by this
package.

## Custom Configuration (Optional)

Custom configuration is only needed if your project has different requirements.
You can extend or override the default configuration by creating your own
`grumphp.yml` or `rector.php` in your project root.

For example:

```yaml
# grumphp.yml
imports:
  - { resource: vendor/sicse/quality-assurance/config/grumphp.yml }
parameters:
  phpstan.level: 8
```

If you provide your own `grumphp.yml`, do not set the GrumPHP
`config-default-path` to the package's default config.

When providing your own `rector.php`, make sure to update the `rector.config`
parameter in your `grumphp.yml` accordingly.

## Contributing

Contributions are welcome! Please open issues or submit pull requests via
GitHub.

## License

GPL-2.0-or-later. See the `LICENSE` file for details.
