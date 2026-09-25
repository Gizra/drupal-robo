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

## Requirements

Requires **ddev >= v1.24.8**, whose reworked Pantheon provider exposes the site
and environment through `DDEV_PANTHEON_SITE` / `DDEV_PANTHEON_ENVIRONMENT` — the
variables the deploy commands read.

On older projects, upgrade ddev, run `ddev restart` to regenerate
`.ddev/providers/pantheon.yaml`, then set both variables in `.ddev/config.yaml`
`web_environment`.

## Assumptions

These commands assume the standard Gizra Drupal layout:

- `web/` docroot, `config/sync`, and `config/po_files`.
- The Pantheon site and environment in `DDEV_PANTHEON_SITE` /
  `DDEV_PANTHEON_ENVIRONMENT` (`.ddev/config.yaml` `web_environment`, or set by
  the deploy job).
- The CLIs the commands shell out to: `git`, `composer`, `drush`, `terminus`,
  `npm`, `rsync`, `curl`.

`BootstrapTrait` (new-project scaffolding) is intentionally **not** part of this
package; it stays in `drupal-starter` as the template-instantiation tool.

### Environment variables

`deploy:notify` needs two variables exported by the deploy job. When
`GITHUB_COMMIT_MESSAGE` is unset it skips loudly and names the variable.

| Variable | Used by | Purpose |
| --- | --- | --- |
| `GITHUB_COMMIT_MESSAGE` | `deploy:notify` | Merge/squash message the PR and issue numbers are parsed from. |
| `GITHUB_TOKEN` | `deploy:notify` | Reads the PR and posts the deployment comment. |

## Development

```bash
composer install
composer check   # phpcs + phpstan + phpunit
```

Tests are pure PHP (no Drupal, no external tools): a composition test proves the
traits assemble into a working `RoboFile`, plus unit tests over the extracted
pure logic.

## Short comments

A comment, or a paragraph in a `.md` file, is at most 30 words. CI measures only
the comments a pull request adds:

```bash
python3 ci-scripts/check_comments.py --base origin/main
```
