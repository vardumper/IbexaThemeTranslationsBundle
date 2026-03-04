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
composer require fork/ibexa-theme-translations-bundle
```

### 2. Register the bundle in your `config/bundles.php`:
The bundle should be registered automatically - if not, activate it in `config/bundles.php`:

```php
return [
    // ...
    vardumper\IbexaThemeTranslationsBundle\IbexaThemeTranslationsBundle::class => ['all' => true],
];
```

## Testing (TBD)

This bundle uses [Pest](https://pestphp.com/) for testing.

### Running Tests (TBD)

First, install the development dependencies:

```bash
composer install --dev
```

Then run the tests:

```bash
./vendor/bin/pest
```

### Test Structure (TBD)

## Roadmap (TBD)

