# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Self-hosted Symfony Flex recipe (`flex/index.json` + `flex/recipes/`): installing the bundle now automatically registers it in `config/bundles.php`, copies the Doctrine entity mapping and routes into `config/`, and copies the migrations into the project's `migrations/` directory.

### Changed

- **Breaking (installation)**: Projects installing via Symfony Flex must point Flex at this repository's recipe endpoint **before** `composer require`, because the bundle is proprietary and not listed in the public recipe index:

  ```bash
  composer config extra.symfony.endpoint https://raw.githubusercontent.com/vardumper/IbexaThemeTranslationsBundle/main/flex/index.json
  ```

  Earlier README versions documented `composer require` alone as sufficient for the full setup. In practice, Symfony Flex only auto-registered the bundle class (via its auto-generated recipe); the Doctrine mapping, routes, and migrations were never copied, so those manual steps were still required. Projects that installed manually (or already have the endpoint configured) are unaffected.

- Removed the redundant `flex/recipe/manifest.json`; the recipe manifest now lives in `flex/recipes/vardumper.ibexa-theme-translations-bundle.1.0.json`.

### Fixed

- Admin UI: Accept, Revert, and Deepl Translate icons did not render (hardcoded `ibexaicons` sprite references replaced with `ibexa_icon_path()`).
