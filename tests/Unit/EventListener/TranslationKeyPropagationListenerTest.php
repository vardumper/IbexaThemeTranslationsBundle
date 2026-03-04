<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;
use fork\IbexaThemeTranslationsBundle\EventListener\TranslationKeyPropagationListener;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use fork\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

uses(PHPUnit\Framework\TestCase::class);

it('ignores postPersist events for non-Translation entities', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $resolver = $this->createMock(LanguageResolverInterface::class);
    $em = $this->createMock(EntityManagerInterface::class);

    $listener = new TranslationKeyPropagationListener($repo, $resolver);

    $args = new PostPersistEventArgs(new \stdClass(), $em);
    $listener->postPersist($args);

    // No keys queued; postFlush should be a no-op
    $flushArgs = new PostFlushEventArgs($em);
    $em->expects($this->never())->method('flush');
    $listener->postFlush($flushArgs);
});

it('queues the trans key on postPersist for Translation entities', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findLanguageCodesForKey')->with('hello')->willReturn(['eng-GB']);

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getUsedLanguages')->willReturn(['eng-GB', 'deu-DE']);

    $em = $this->createMock(EntityManagerInterface::class);
    // Expects one persist (for 'deu-DE' which is missing)
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('flush');

    $listener = new TranslationKeyPropagationListener($repo, $resolver);

    $translation = new Translation('eng-GB', 'hello');
    $persistArgs = new PostPersistEventArgs($translation, $em);
    $listener->postPersist($persistArgs);

    $flushArgs = new PostFlushEventArgs($em);
    $listener->postFlush($flushArgs);
});

it('postFlush is a no-op when no keys are pending', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $resolver = $this->createMock(LanguageResolverInterface::class);
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->never())->method('flush');

    $listener = new TranslationKeyPropagationListener($repo, $resolver);
    $listener->postFlush(new PostFlushEventArgs($em));
});

it('does not persist a stub when all active languages already have the key', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findLanguageCodesForKey')->willReturn(['eng-GB', 'deu-DE']);

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getUsedLanguages')->willReturn(['eng-GB', 'deu-DE']);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->never())->method('persist');
    $em->expects($this->never())->method('flush');

    $listener = new TranslationKeyPropagationListener($repo, $resolver);

    $translation = new Translation('eng-GB', 'hello');
    $listener->postPersist(new PostPersistEventArgs($translation, $em));
    $listener->postFlush(new PostFlushEventArgs($em));
});
