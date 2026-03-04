<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

final class DefaultTranslationsFixture extends Fixture
{
    public function __construct(
        private readonly TranslationRepository $translationRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $translationData = [
            'example.key' => 'Hello World',
        ];

        $this->addAllIfNotExist($manager, $translationData);
        $manager->flush();
        $manager->clear();
    }

    private function addAllIfNotExist(ObjectManager $manager, array $data): void
    {
        foreach ($data as $key => $value) {
            if (
                $this->translationRepository->findOneBy([
                    'languageCode' => 'eng-GB',
                    'transKey' => $key,
                ]) === null
            ) {
                $manager->persist(new Translation('eng-GB', $key, $value));
            }
        }
    }
}
