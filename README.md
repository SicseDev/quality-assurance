# Quality Assurance

A PHP package providing standardized code quality tools and configurations for 
PHP projects, with a focus on Drupal best practices.

## Requirements

- PHP 8.3 or higher
- Composer 2.6+
- Compatible with Drupal 10 and 11

## Installation

Require the package as a development dependency:

```bash
composer require sicse/quality-assurance --dev
```

During installation, you will be prompted to enable automatic integration with 
GrumPHP. If enabled, GrumPHP and all related code quality tasks are configured 
to run automatically on every Git commit, eliminating the need for manual setup
for standard use.

## Features

- Out-of-the-box GrumPHP integration: code quality checks run automatically on 
  Git commits
- Rector configuration for automated code refactoring
- Integration with industry-standard PHP tools:
  - PHP_CodeSniffer (Drupal and DrupalPractices standards)
  - PHPStan (static analysis)
  - PHP-CS-Fixer
  - PHPUnit
  - Twig CS Fixer
  - And more

## Usage

After installation and enabling the integration, GrumPHP is automatically 
configured. Code quality checks will run on every Git commit. You can also run 
them manually:

```bash
vendor/bin/grumphp run
```

## Custom Configuration (Optional)

Custom configuration is only needed if your project has different requirements.
You can extend or override the default configuration by creating your own 
`grumphp.yml` or `rector.php` in your project root. For example:

```yaml
# grumphp.yml
imports:
  - { resource: vendor/sicse/quality-assurance/config/grumphp.yml }
parameters:
  phpstan.level: 8
```

## Contributing

Contributions are welcome! Please open issues or submit pull requests via 
GitHub.

## License

GPL-2.0-or-later. See the `LICENSE` file for details.
