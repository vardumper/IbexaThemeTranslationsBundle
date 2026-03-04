<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class IbexaThemeTranslationsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ibexa_theme_translations.cache_dir', $config['cache_dir']);
        $container->setParameter('ibexa_theme_translations.default_language', $config['default_language']);
        $container->setParameter('ibexa_theme_translations.redis.enabled', $config['redis']['enabled']);
        $container->setParameter('ibexa_theme_translations.redis.prefix', $config['redis']['prefix']);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');
    }
}
