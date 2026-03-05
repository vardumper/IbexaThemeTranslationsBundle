# IbexaThemeTranslationsBundle

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

```bash
composer require vardumper/ibexa-theme-translations-bundle
```

### 2. Register the bundle in your `config/bundles.php`:
The bundle should be registered automatically - if not, activate it in `config/bundles.php`:

```php
return [
    // ...
    vardumper\IbexaThemeTranslationsBundle\IbexaThemeTranslationsBundle::class => ['all' => true],
];
```

### 3. Register Entities
```yaml
# config/packages/doctrine.yaml
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

### 4. Register Routes
```yaml
# config/routes/ibexa_theme_translations.yaml
ibexa_theme_translations:
    resource: '@IbexaThemeTranslationsBundle/config/routes.yaml'
```

### 5. Update DB Schema
```bash
bin/console doctrine:schema:update --em=default --force
```

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
