<table align="center" style="border-collapse:collapse !important; border:none !important;">
  <tr style="border:0px none; border-top: 0px none !important;">
    <td align="center" valign="middle" style="padding:0 1rem; border:none !important;">
      <a href="https://ibexa.co" target="_blank">
        <img src="https://vardumper.github.io/extended-htmldocument/logo-ibexa.svg" style="display:block; height:75px; width:auto; max-width:300px;" alt="Ibexa Logo" />
      </a>
    </td>
  </tr>
</table>
<h1 align="center">IbexaThemeTranslationsBundle</h1>

<p align="center" dir="auto">
    <a href="https://packagist.org/packages/vardumper/ibexa-theme-translations-bundle" rel="nofollow">
        <img src="https://poser.pugx.org/vardumper/ibexa-theme-translations-bundle/v/stable" />
    </a>
    <img src="https://raw.githubusercontent.com/vardumper/IbexaThemeTranslationsBundle/refs/heads/main/coverage.svg" alt="Code Coverage" />
</p>

This is a bundle for Ibexa DXP. It allows managing string translations for use in themes outside Ibexa's regular content object and translation logic.
While there are a couple of i18n concepts pre-included with Ibexa, they lack a user interface. So here's a simple Doctrine ORM based approach with a UI, string search, Deepl intehration, and very basic approval flow for translation editors.

## Requirements
* Ibexa DXP >= v5.0
* Ibexa DXP >= v4.4 (untested)
* Ibexa Automated Translations installed, activated and configured [ibexa/automated-translation](https://packagist.org/packages/ibexa/automated-translation) if you want to use Deepl Translations

## Features
* Supports Deepl Free API Key - by replacing the Ibexas' default Deepl Client
* 3-layered caching: OPCache via static PHP Array and Redis if available
* Event Listeners warm caches when there are changes
* Brings a console command for cache warming
* Supports headless frontends by providing JSON and Typescript language files
* Allows importing/exporting translations to and from CSV
* Supports Doctrine Fixtures to pre-popultae translations.
* Supports Ibexa DXP v5.0+ and v4.4+

## Installation

### 1. Install the bundle

If your project uses [Symfony Flex](https://symfony.com/doc/current/setup/flex.html) (recommended), the bundle, its configuration, and routes are registered automatically:

```bash
composer require vardumper/ibexa-theme-translations-bundle
```

### 2. Update DB Schema
```bash
bin/console doctrine:schema:update --em=default --force
```

---

<details>
<summary>Manual installation (without Symfony Flex)</summary>

### Register the bundle in your `config/bundles.php`:

```php
return [
    // ...
    vardumper\IbexaThemeTranslationsBundle\IbexaThemeTranslationsBundle::class => ['all' => true],
];
```

### Register Entities
```yaml
# config/packages/ibexa_theme_translations.yaml
doctrine:
    orm:
        mappings:
            IbexaThemeTranslationsBundle:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/vendor/vardumper/ibexa-theme-translations-bundle/src/Entity'
                prefix: 'vardumper\IbexaThemeTranslationsBundle\Entity'
                alias: IbexaThemeTranslations
```

### Register Routes
```yaml
# config/routes/ibexa_theme_translations.yaml
ibexa_theme_translations:
    resource: '@IbexaThemeTranslationsBundle/config/routes.yaml'
```

### Update DB Schema
```bash
bin/console doctrine:schema:update --em=default --force
```

</details>

## Run Tests
This library is fully unit tested with PEST. You can run the tests by executing the following commands in the root directory of the project.

### Unit Tests
```
vendor/bin/pest
```

### Coverage Report
You can also generate a coverage report by running the following command.
```
XDEBUG_MODE=coverage vendor/bin/pest --coverage-html=coverage-report
```
