# Gizra Drupal Robo

Shared [Robo](https://robo.li) commands for Gizra Drupal projects — deployment
to Pantheon, theme compilation, release notes, security checks, module
auto-updates and translation management. Extracted so every project pulls fixes
and improvements from one place.

## Installation

The package is distributed straight from its Git repository (no Packagist).
Add it as a `vcs` repository and require it as a dev dependency:

```jsonc
// composer.json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Gizra/drupal-robo" }
]
```

```bash
composer require --dev gizra/drupal-robo:^1.0
composer update gizra/drupal-robo   # pull the latest tagged release later
```

## Usage

Your project's `RoboFile` composes the traits it needs plus `ProjectConfigTrait`,
and implements the three required getters. Everything else has a default.

```php
<?php

declare(strict_types=1);

use Robo\Tasks;
use RoboComponents\AutoUpdateTrait;
use RoboComponents\DeploymentTrait;
use RoboComponents\PhpcsTrait;
use RoboComponents\ProjectConfigTrait;
use RoboComponents\ThemeTrait;
use RoboComponents\TranslationManagement\ImportToUi;
// ... other traits as needed.

class RoboFile extends Tasks {

  use AutoUpdateTrait;
  use DeploymentTrait;
  use ImportToUi;
  use PhpcsTrait;
  use ProjectConfigTrait;
  use ThemeTrait;

  protected function getThemeName(): string {
    return 'server_theme';
  }

  protected function getGithubProject(): string {
    return 'Gizra/drupal-starter';
  }

  protected function getInstalledLanguages(): array {
    return ['ar', 'es'];
  }

  // The Drupal bootstrap in the constructor stays in your project.
}
```

### Configuration contract

| Getter | Required | Default |
| --- | --- | --- |
| `getThemeName()` | ✅ | — |
| `getGithubProject()` | ✅ | — |
| `getInstalledLanguages()` | ✅ | — |
| `getThemeBasePath()` | | `web/themes/custom/<theme name>` |
| `getAdminUser()` | | `AdminOne` |
| `getPoFilesPath()` | | `config/po_files` |
| `getDeploySyncExcludes()` | | derived list (theme paths follow `getThemeBasePath()`) |

Override any defaulted getter in your `RoboFile` only when the project diverges.

## Assumptions

These commands assume the standard Gizra Drupal layout — `web/` docroot,
`.ddev/providers/pantheon.yaml` with `environment_variables.project`,
`config/sync`, `config/po_files`, and the external CLIs the commands shell out
to (`git`, `composer`, `drush`, `terminus`, `npm`, `rsync`, `curl`).

`BootstrapTrait` (new-project scaffolding) is intentionally **not** part of this
package; it stays in `drupal-starter` as the template-instantiation tool.

## Development

```bash
composer install
composer check   # phpcs + phpstan + phpunit
```

Tests are pure PHP (no Drupal, no external tools): a composition test proves the
traits assemble into a working `RoboFile`, plus unit tests over the extracted
pure logic.
