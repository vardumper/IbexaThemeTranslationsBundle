<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;

#[AsCommand(
    name: 'theme-translations:cache:warmup',
    description: 'Warm up the theme translations cache (static PHP files + Redis + public JSON/TS for headless frontends)',
)]
final class WarmupTranslationCacheCommand extends Command
{
    public function __construct(
        private readonly TranslationCacheWarmer $warmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear all caches before warming')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Only warm a specific language code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clear = $input->getOption('clear');
        $language = $input->getOption('language');

        if ($clear) {
            $io->info('Clearing all translation caches...');
            $this->warmer->clearAll();
        }

        if ($language) {
            $io->info(sprintf('Warming translation cache for language: %s', $language));
            $this->warmer->warmLanguage($language);
        } else {
            $io->info('Warming translation cache for all languages...');
            $this->warmer->warmAll();
        }

        $io->success('Translation cache warmup complete.');

        return Command::SUCCESS;
    }
}
